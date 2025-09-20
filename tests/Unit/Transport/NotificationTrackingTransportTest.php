<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Tests\Unit\Transport;

use Doctrine\ORM\EntityManagerInterface;
use Nkamuo\NotificationTrackerBundle\Entity\QueuedMessage;
use Nkamuo\NotificationTrackerBundle\Repository\QueuedMessageRepository;
use Nkamuo\NotificationTrackerBundle\Service\NotificationAnalyticsCollector;
use Nkamuo\NotificationTrackerBundle\Stamp\NotificationCampaignStamp;
use Nkamuo\NotificationTrackerBundle\Stamp\NotificationProviderStamp;
use Nkamuo\NotificationTrackerBundle\Stamp\NotificationTemplateStamp;
use Nkamuo\NotificationTrackerBundle\Transport\NotificationTrackingTransport;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class NotificationTrackingTransportTest extends TestCase
{
    private NotificationTrackingTransport $transport;
    private EntityManagerInterface&MockObject $entityManager;
    private QueuedMessageRepository&MockObject $repository;
    private SerializerInterface&MockObject $serializer;
    private NotificationAnalyticsCollector&MockObject $analyticsCollector;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(QueuedMessageRepository::class);
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->analyticsCollector = $this->createMock(NotificationAnalyticsCollector::class);

        $options = [
            'transport_name' => 'test_transport',
            'queue_name' => 'test_queue',
            'analytics_enabled' => true,
            'provider_aware_routing' => true,
            'batch_size' => 5,
            'max_retries' => 3,
            'retry_delays' => [1000, 5000, 30000],
        ];

        $this->transport = new NotificationTrackingTransport(
            $this->entityManager,
            $this->repository,
            $this->serializer,
            $this->analyticsCollector,
            $options
        );
    }

    public function testSendSingleMessage(): void
    {
        $message = new TestMessage('Hello World');
        $envelope = new Envelope($message, [
            new NotificationProviderStamp('email', 10),
            new NotificationCampaignStamp('campaign-123'),
            new NotificationTemplateStamp('template-456'),
        ]);

        $this->serializer
            ->expects($this->once())
            ->method('encode')
            ->with($envelope)
            ->willReturn([
                'body' => 'serialized-body',
                'headers' => ['Content-Type' => 'application/json'],
            ]);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(QueuedMessage::class));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->analyticsCollector
            ->expects($this->once())
            ->method('recordMessageQueued')
            ->with(
                $this->isInstanceOf(QueuedMessage::class),
                'email',
                'campaign-123',
                'template-456'
            );

        $result = $this->transport->send($envelope);

        $this->assertSame($envelope, $result);
    }

    public function testSendBatchMessages(): void
    {
        $messages = [];
        $envelopes = [];

        for ($i = 0; $i < 3; $i++) {
            $message = new TestMessage("Message $i");
            $envelope = new Envelope($message, [
                new NotificationProviderStamp('sms', 5),
            ]);
            $messages[] = $message;
            $envelopes[] = $envelope;
        }

        $this->serializer
            ->expects($this->exactly(3))
            ->method('encode')
            ->willReturn([
                'body' => 'serialized-body',
                'headers' => ['Content-Type' => 'application/json'],
            ]);

        $this->entityManager
            ->expects($this->exactly(3))
            ->method('persist')
            ->with($this->isInstanceOf(QueuedMessage::class));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->analyticsCollector
            ->expects($this->exactly(3))
            ->method('recordMessageQueued');

        $results = $this->transport->send(...$envelopes);

        $this->assertCount(3, $results);
        foreach ($results as $i => $result) {
            $this->assertSame($envelopes[$i], $result);
        }
    }

    public function testSendWithDelayStamp(): void
    {
        $message = new TestMessage('Delayed message');
        $delayStamp = new DelayStamp(5000); // 5 seconds
        $envelope = new Envelope($message, [$delayStamp]);

        $this->serializer
            ->expects($this->once())
            ->method('encode')
            ->willReturn([
                'body' => 'serialized-body',
                'headers' => ['Content-Type' => 'application/json'],
            ]);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (QueuedMessage $queuedMessage) {
                $availableAt = $queuedMessage->getAvailableAt();
                $now = new \DateTimeImmutable();
                $expectedTime = $now->modify('+5 seconds');
                
                // Allow 1 second tolerance for test execution time
                return abs($availableAt->getTimestamp() - $expectedTime->getTimestamp()) <= 1;
            }));

        $this->transport->send($envelope);
    }

    public function testGetFromReceiver(): void
    {
        $queuedMessage = $this->createQueuedMessage();
        $envelope = new Envelope(new TestMessage('Test'), [new ReceivedStamp('test-transport')]);

        $this->repository
            ->expects($this->once())
            ->method('findNextBatch')
            ->with('test_transport', 'test_queue', 5)
            ->willReturn([$queuedMessage]);

        $this->serializer
            ->expects($this->once())
            ->method('decode')
            ->with([
                'body' => 'serialized-body',
                'headers' => ['Content-Type' => 'application/json'],
            ])
            ->willReturn($envelope);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (QueuedMessage $msg) {
                return $msg->getStatus() === 'processing';
            }));

        $envelopes = $this->transport->get();

        $this->assertCount(1, $envelopes);
        $this->assertInstanceOf(Envelope::class, $envelopes[0]);
    }

    public function testAck(): void
    {
        $queuedMessage = $this->createQueuedMessage();
        $envelope = new Envelope(new TestMessage('Test'), [
            new ReceivedStamp('test-transport'),
            new QueuedMessageIdStamp($queuedMessage->getId()->toString()),
        ]);

        $this->repository
            ->expects($this->once())
            ->method('find')
            ->with($queuedMessage->getId()->toString())
            ->willReturn($queuedMessage);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (QueuedMessage $msg) {
                return $msg->getStatus() === 'processed' && 
                       $msg->getProcessedAt() instanceof \DateTimeImmutable;
            }));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->analyticsCollector
            ->expects($this->once())
            ->method('recordMessageProcessed')
            ->with($queuedMessage);

        $this->transport->ack($envelope);
    }

    public function testReject(): void
    {
        $queuedMessage = $this->createQueuedMessage();
        $envelope = new Envelope(new TestMessage('Test'), [
            new ReceivedStamp('test-transport'),
            new QueuedMessageIdStamp($queuedMessage->getId()->toString()),
        ]);

        $this->repository
            ->expects($this->once())
            ->method('find')
            ->with($queuedMessage->getId()->toString())
            ->willReturn($queuedMessage);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (QueuedMessage $msg) {
                return $msg->getStatus() === 'failed' &&
                       $msg->getFailedAt() instanceof \DateTimeImmutable &&
                       $msg->getRetryCount() === 1;
            }));

        $this->analyticsCollector
            ->expects($this->once())
            ->method('recordMessageFailed')
            ->with($queuedMessage);

        $this->transport->reject($envelope);
    }

    public function testRejectWithRetry(): void
    {
        $queuedMessage = $this->createQueuedMessage();
        $queuedMessage->setRetryCount(1); // Already retried once

        $envelope = new Envelope(new TestMessage('Test'), [
            new ReceivedStamp('test-transport'),
            new QueuedMessageIdStamp($queuedMessage->getId()->toString()),
        ]);

        $this->repository
            ->expects($this->once())
            ->method('find')
            ->willReturn($queuedMessage);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (QueuedMessage $msg) {
                return $msg->getStatus() === 'pending' &&
                       $msg->getRetryCount() === 2 &&
                       $msg->getAvailableAt() > new \DateTimeImmutable();
            }));

        $this->transport->reject($envelope);
    }

    public function testRejectExceedsMaxRetries(): void
    {
        $queuedMessage = $this->createQueuedMessage();
        $queuedMessage->setRetryCount(3); // At max retries

        $envelope = new Envelope(new TestMessage('Test'), [
            new ReceivedStamp('test-transport'),
            new QueuedMessageIdStamp($queuedMessage->getId()->toString()),
        ]);

        $this->repository
            ->expects($this->once())
            ->method('find')
            ->willReturn($queuedMessage);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (QueuedMessage $msg) {
                return $msg->getStatus() === 'failed' &&
                       $msg->getFailedAt() instanceof \DateTimeImmutable;
            }));

        $this->transport->reject($envelope);
    }

    public function testGetMessageCount(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('getMessageCount')
            ->with('test_transport', 'test_queue')
            ->willReturn(42);

        $count = $this->transport->getMessageCount();

        $this->assertEquals(42, $count);
    }

    public function testGetPendingMessageCount(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('getMessageCountByStatus')
            ->with('test_transport', 'test_queue', 'pending')
            ->willReturn(15);

        $count = $this->transport->getMessageCount();

        $this->assertEquals(15, $count);
    }

    public function testErrorHandlingOnSend(): void
    {
        $message = new TestMessage('Test');
        $envelope = new Envelope($message);

        $this->serializer
            ->expects($this->once())
            ->method('encode')
            ->willThrowException(new \Exception('Serialization failed'));

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Failed to send message: Serialization failed');

        $this->transport->send($envelope);
    }

    private function createQueuedMessage(): QueuedMessage
    {
        $queuedMessage = new QueuedMessage();
        $queuedMessage->setTransportName('test_transport');
        $queuedMessage->setQueueName('test_queue');
        $queuedMessage->setMessageBody('serialized-body');
        $queuedMessage->setMessageHeaders(['Content-Type' => 'application/json']);
        $queuedMessage->setStatus('pending');
        $queuedMessage->setCreatedAt(new \DateTimeImmutable());
        $queuedMessage->setAvailableAt(new \DateTimeImmutable());

        return $queuedMessage;
    }
}

class TestMessage
{
    public function __construct(public string $content)
    {
    }
}

class QueuedMessageIdStamp
{
    public function __construct(public string $id)
    {
    }
}
