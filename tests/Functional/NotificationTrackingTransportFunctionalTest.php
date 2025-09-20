<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Nkamuo\NotificationTrackerBundle\Entity\QueuedMessage;
use Nkamuo\NotificationTrackerBundle\Repository\QueuedMessageRepository;
use Nkamuo\NotificationTrackerBundle\Stamp\NotificationCampaignStamp;
use Nkamuo\NotificationTrackerBundle\Stamp\NotificationProviderStamp;
use Nkamuo\NotificationTrackerBundle\Stamp\NotificationTemplateStamp;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\TransportInterface;

class NotificationTrackingTransportFunctionalTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private QueuedMessageRepository $repository;
    private TransportInterface $transport;
    private MessageBusInterface $messageBus;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->repository = $container->get(QueuedMessageRepository::class);
        $this->transport = $container->get('messenger.transport.notification_test');
        $this->messageBus = $container->get(MessageBusInterface::class);

        // Clear any existing messages
        $this->entityManager->createQuery('DELETE FROM ' . QueuedMessage::class)->execute();
        $this->entityManager->flush();
    }

    public function testCompleteMessageLifecycle(): void
    {
        // 1. Send a message
        $message = new TestNotificationMessage('Hello, World!');
        $envelope = new Envelope($message, [
            new NotificationProviderStamp('email', 10),
            new NotificationCampaignStamp('welcome-campaign', ['source' => 'signup']),
            new NotificationTemplateStamp('welcome-template', 'v2.1'),
        ]);

        $sentEnvelope = $this->transport->send($envelope);
        $this->assertInstanceOf(Envelope::class, $sentEnvelope);

        // 2. Verify message was persisted
        $queuedMessages = $this->repository->findAll();
        $this->assertCount(1, $queuedMessages);

        $queuedMessage = $queuedMessages[0];
        $this->assertEquals('test_transport', $queuedMessage->getTransport());
        $this->assertEquals('email', $queuedMessage->getNotificationProvider());
        $this->assertEquals('welcome-campaign', $queuedMessage->getCampaignId());
        $this->assertEquals('welcome-template', $queuedMessage->getTemplateId());
        $this->assertEquals(10, $queuedMessage->getPriority());
        $this->assertEquals('queued', $queuedMessage->getStatus());

        // 3. Receive the message
        $receivedEnvelopes = $this->transport->get();
        $this->assertCount(1, $receivedEnvelopes);

        $receivedEnvelope = $receivedEnvelopes[0];
        $this->assertInstanceOf(TestNotificationMessage::class, $receivedEnvelope->getMessage());

        // 4. Verify message status changed to processing
        $this->entityManager->refresh($queuedMessage);
        $this->assertEquals('processing', $queuedMessage->getStatus());

        // 5. Acknowledge the message
        $this->transport->ack($receivedEnvelope);

        // 6. Verify message was marked as processed
        $this->entityManager->refresh($queuedMessage);
        $this->assertEquals('processed', $queuedMessage->getStatus());
        $this->assertNotNull($queuedMessage->getProcessedAt());
    }

    public function testMessageWithDelay(): void
    {
        $message = new TestNotificationMessage('Delayed message');
        $delayStamp = new DelayStamp(5000); // 5 seconds
        $envelope = new Envelope($message, [$delayStamp]);

        $this->transport->send($envelope);

        $queuedMessages = $this->repository->findAll();
        $this->assertCount(1, $queuedMessages);

        $queuedMessage = $queuedMessages[0];
        $expectedAvailableAt = new \DateTimeImmutable('+5 seconds');
        
        // Allow 2 second tolerance
        $this->assertEqualsWithDelta(
            $expectedAvailableAt->getTimestamp(),
            $queuedMessage->getAvailableAt()->getTimestamp(),
            2
        );
    }

    public function testMessageRejectionAndRetry(): void
    {
        $message = new TestNotificationMessage('Test retry');
        $envelope = new Envelope($message);

        // Send message
        $this->transport->send($envelope);

        // Receive message
        $receivedEnvelopes = $this->transport->get();
        $receivedEnvelope = $receivedEnvelopes[0];

        // Reject the message (simulating failure)
        $this->transport->reject($receivedEnvelope);

        // Verify retry was scheduled
        $queuedMessages = $this->repository->findAll();
        $queuedMessage = $queuedMessages[0];
        
        $this->assertEquals('pending', $queuedMessage->getStatus());
        $this->assertEquals(1, $queuedMessage->getRetryCount());
        $this->assertGreaterThan(new \DateTimeImmutable(), $queuedMessage->getAvailableAt());
    }

    public function testProviderAwareRouting(): void
    {
        // Send messages for different providers
        $emailMessage = new TestNotificationMessage('Email message');
        $smsMessage = new TestNotificationMessage('SMS message');

        $this->transport->send(new Envelope($emailMessage, [
            new NotificationProviderStamp('email', 5)
        ]));

        $this->transport->send(new Envelope($smsMessage, [
            new NotificationProviderStamp('sms', 10)
        ]));

        // Verify messages were stored with correct providers
        $emailMessages = $this->repository->findBy(['notificationProvider' => 'email']);
        $smsMessages = $this->repository->findBy(['notificationProvider' => 'sms']);

        $this->assertCount(1, $emailMessages);
        $this->assertCount(1, $smsMessages);

        $this->assertEquals(5, $emailMessages[0]->getPriority());
        $this->assertEquals(10, $smsMessages[0]->getPriority());
    }

    public function testBatchProcessing(): void
    {
        // Send multiple messages
        $messages = [];
        for ($i = 0; $i < 10; $i++) {
            $message = new TestNotificationMessage("Message $i");
            $envelope = new Envelope($message, [
                new NotificationProviderStamp('batch_test', $i)
            ]);
            $messages[] = $envelope;
        }

        // Send all messages at once
        $this->transport->send(...$messages);

        // Verify all messages were persisted
        $queuedMessages = $this->repository->findAll();
        $this->assertCount(10, $queuedMessages);

        // Verify batch retrieval respects batch size (assuming batch_size=5 in config)
        $batch1 = $this->transport->get();
        $this->assertLessThanOrEqual(5, count($batch1));
    }

    public function testMessageCounting(): void
    {
        // Send some messages
        for ($i = 0; $i < 7; $i++) {
            $message = new TestNotificationMessage("Count test $i");
            $this->transport->send(new Envelope($message));
        }

        // Test message counting
        if (method_exists($this->transport, 'getMessageCount')) {
            $count = $this->transport->getMessageCount();
            $this->assertEquals(7, $count);
        }

        // Process some messages
        $received = $this->transport->get();
        foreach (array_slice($received, 0, 3) as $envelope) {
            $this->transport->ack($envelope);
        }

        // Verify counting reflects processed messages
        $pendingCount = $this->repository->count(['status' => 'queued']);
        $processedCount = $this->repository->count(['status' => 'processed']);
        
        $this->assertEquals(4, $pendingCount);
        $this->assertEquals(3, $processedCount);
    }

    public function testMaxRetriesExceeded(): void
    {
        $message = new TestNotificationMessage('Max retries test');
        $envelope = new Envelope($message);

        // Send message
        $this->transport->send($envelope);

        $queuedMessage = $this->repository->findAll()[0];

        // Simulate multiple failures
        for ($i = 0; $i < 4; $i++) { // Assuming max_retries = 3
            $receivedEnvelopes = $this->transport->get();
            if (!empty($receivedEnvelopes)) {
                $this->transport->reject($receivedEnvelopes[0]);
            }
            $this->entityManager->refresh($queuedMessage);
        }

        // Message should be permanently failed
        $this->assertEquals('failed', $queuedMessage->getStatus());
        $this->assertEquals(3, $queuedMessage->getRetryCount());
        $this->assertNotNull($queuedMessage->getErrorMessage());
    }

    public function testAnalyticsIntegration(): void
    {
        $message = new TestNotificationMessage('Analytics test');
        $envelope = new Envelope($message, [
            new NotificationProviderStamp('email', 5),
            new NotificationCampaignStamp('analytics-campaign'),
        ]);

        // Send and process message
        $this->transport->send($envelope);
        $received = $this->transport->get();
        $this->transport->ack($received[0]);

        // Verify analytics data was recorded
        $queuedMessage = $this->repository->findAll()[0];
        $this->assertNotNull($queuedMessage->getProcessingMetadata());
        
        $metadata = $queuedMessage->getProcessingMetadata();
        $this->assertArrayHasKey('processed_at', $metadata);
        $this->assertArrayHasKey('provider', $metadata);
        $this->assertEquals('email', $metadata['provider']);
    }
}

class TestNotificationMessage
{
    public function __construct(public string $content)
    {
    }

    public function __toString(): string
    {
        return $this->content;
    }
}
