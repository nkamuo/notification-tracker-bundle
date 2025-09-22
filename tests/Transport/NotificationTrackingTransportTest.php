<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Tests\Transport;

use Doctrine\ORM\EntityManagerInterface;
use Nkamuo\NotificationTrackerBundle\Entity\QueuedMessage;
use Nkamuo\NotificationTrackerBundle\Repository\QueuedMessageRepository;
use Nkamuo\NotificationTrackerBundle\Service\NotificationAnalyticsCollector;
use Nkamuo\NotificationTrackerBundle\Messenger\Stamp\NotificationProviderStamp;
use Nkamuo\NotificationTrackerBundle\Messenger\Stamp\NotificationCampaignStamp;
use Nkamuo\NotificationTrackerBundle\Transport\NotificationTrackingTransport;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

class NotificationTrackingTransportTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private QueuedMessageRepository $repository;
    private NotificationAnalyticsCollector $analyticsCollector;
    private NotificationTrackingTransport $transport;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(QueuedMessageRepository::class);
        $this->analyticsCollector = $this->createMock(NotificationAnalyticsCollector::class);

        $this->transport = new NotificationTrackingTransport(
            $this->entityManager,
            $this->repository,
            new PhpSerializer(),
            $this->analyticsCollector,
            [
                'transport_name' => 'test',
                'queue_name' => 'test_queue',
                'analytics_enabled' => true,
                'provider_aware_routing' => true,
                'batch_size' => 5,
                'max_retries' => 3,
            ]
        );
    }

    public function testSendMessage(): void
    {
        $message = new TestMessage('test data');
        $envelope = new Envelope($message);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(QueuedMessage::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->analyticsCollector->expects($this->once())
            ->method('recordMessageQueued');

        $resultEnvelope = $this->transport->send($envelope);

        $this->assertInstanceOf(Envelope::class, $resultEnvelope);
    }

    public function testSendMessageWithStamps(): void
    {
        $message = new TestMessage('test data');
        $envelope = new Envelope($message, [
            new NotificationProviderStamp('email', 10),
            new NotificationCampaignStamp('test-campaign'),
            new DelayStamp(5000), // 5 seconds
        ]);

        $capturedQueuedMessage = null;
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function ($entity) use (&$capturedQueuedMessage) {
                $capturedQueuedMessage = $entity;
            });

        $this->transport->send($envelope);

        $this->assertInstanceOf(QueuedMessage::class, $capturedQueuedMessage);
        $this->assertEquals('email', $capturedQueuedMessage->getNotificationProvider());
        $this->assertEquals('test-campaign', $capturedQueuedMessage->getCampaignId());
        $this->assertEquals(10, $capturedQueuedMessage->getPriority());
        $this->assertNotNull($capturedQueuedMessage->getAvailableAt());
    }

    public function testProviderAwareRouting(): void
    {
        $message = new TestMessage('test data');
        $envelope = new Envelope($message, [
            new NotificationProviderStamp('sms'),
        ]);

        $capturedQueuedMessage = null;
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function ($entity) use (&$capturedQueuedMessage) {
                $capturedQueuedMessage = $entity;
            });

        $this->transport->send($envelope);

        // Should route to provider-specific queue
        $this->assertEquals('test_queue_sms', $capturedQueuedMessage->getQueueName());
    }

    public function testGetMessages(): void
    {
        $queuedMessage = new QueuedMessage();
        $queuedMessage->setTransport('test');
        $queuedMessage->setQueueName('test_queue');
        $queuedMessage->setBody('serialized_message_body');
        $queuedMessage->setHeaders(['header1' => 'value1']);

        $this->repository->expects($this->once())
            ->method('findAvailableMessages')
            ->willReturn([$queuedMessage]);

        $this->repository->expects($this->once())
            ->method('findRetryableMessages')
            ->willReturn([]);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->analyticsCollector->expects($this->once())
            ->method('recordMessageDequeued');

        $messages = iterator_to_array($this->transport->get());

        $this->assertCount(1, $messages);
        $this->assertInstanceOf(Envelope::class, $messages[0]);
    }

    public function testMessageCount(): void
    {
        $this->repository->expects($this->once())
            ->method('getQueueStatistics')
            ->with('test', 'test_queue')
            ->willReturn([
                'queued' => 5,
                'retrying' => 2,
                'processed' => 10,
                'failed' => 1,
            ]);

        $count = $this->transport->getMessageCount();

        $this->assertEquals(7, $count); // queued + retrying
    }
}

class TestMessage
{
    public function __construct(public readonly string $data) {}
}
