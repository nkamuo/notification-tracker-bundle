<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Nkamuo\NotificationTrackerBundle\Entity\Notification;
use Nkamuo\NotificationTrackerBundle\Entity\Message;
use Nkamuo\NotificationTrackerBundle\Service\NotificationSender;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

class MessengerSchedulingIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private NotificationSender $notificationSender;
    private MessageBusInterface $messageBus;

    protected function setUp(): void
    {
        self::bootKernel();
        
        $this->entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $this->notificationSender = static::getContainer()->get(NotificationSender::class);
        $this->messageBus = static::getContainer()->get('messenger.default_bus');
    }

    public function testNotificationCreationAndScheduling(): void
    {
        // Create a notification
        $notification = new Notification();
        $notification->setType('test_notification');
        $notification->setSubject('Test Notification');
        $notification->setContent('This is a test notification');
        $notification->setChannels(['email']);
        $notification->setRecipients([
            ['email' => 'test@example.com', 'channel' => 'email']
        ]);
        $notification->setStatus(Notification::STATUS_DRAFT);
        $notification->setDirection(Notification::DIRECTION_DRAFT);
        
        $this->entityManager->persist($notification);
        $this->entityManager->flush();
        
        // Test status methods
        $this->assertTrue($notification->isDraft());
        $this->assertFalse($notification->isScheduled());
        $this->assertFalse($notification->isSent());
        $this->assertFalse($notification->isFailed());
        
        $notificationId = $notification->getId();
        $this->assertNotNull($notificationId);
    }

    public function testNotificationSendingImmediately(): void
    {
        // Create notification
        $notification = new Notification();
        $notification->setType('immediate_test');
        $notification->setSubject('Immediate Test');
        $notification->setContent('Send immediately');
        $notification->setChannels(['email']);
        $notification->setRecipients([
            ['email' => 'immediate@example.com', 'channel' => 'email']
        ]);
        $notification->setStatus(Notification::STATUS_DRAFT);
        
        $this->entityManager->persist($notification);
        $this->entityManager->flush();
        
        // Send immediately
        $result = $this->notificationSender->sendNotification($notification);
        
        $this->assertTrue($result['success']);
        $this->assertFalse($result['scheduled']);
        $this->assertEquals(0, $result['delay_ms']);
        $this->assertEquals(Notification::STATUS_QUEUED, $notification->getStatus());
    }

    public function testNotificationSchedulingWithDelay(): void
    {
        // Create notification with future scheduling
        $notification = new Notification();
        $notification->setType('scheduled_test');
        $notification->setSubject('Scheduled Test');
        $notification->setContent('Send in the future');
        $notification->setChannels(['email']);
        $notification->setRecipients([
            ['email' => 'scheduled@example.com', 'channel' => 'email']
        ]);
        $notification->setStatus(Notification::STATUS_DRAFT);
        $notification->setScheduledAt(new \DateTimeImmutable('+1 hour'));
        
        $this->entityManager->persist($notification);
        $this->entityManager->flush();
        
        // Send with scheduling
        $result = $this->notificationSender->sendNotification($notification);
        
        $this->assertTrue($result['success']);
        $this->assertTrue($result['scheduled']);
        $this->assertGreaterThan(0, $result['delay_ms']);
        $this->assertEquals(Notification::STATUS_SCHEDULED, $notification->getStatus());
    }

    public function testNotificationStatusTransitions(): void
    {
        $notification = new Notification();
        $notification->setStatus(Notification::STATUS_DRAFT);
        
        // Test all status transitions
        $this->assertTrue($notification->isDraft());
        
        $notification->setStatus(Notification::STATUS_SCHEDULED);
        $this->assertTrue($notification->isScheduled());
        $this->assertFalse($notification->isDraft());
        
        $notification->setStatus(Notification::STATUS_QUEUED);
        $this->assertTrue($notification->isQueued());
        $this->assertFalse($notification->isScheduled());
        
        $notification->setStatus(Notification::STATUS_SENDING);
        $this->assertEquals(Notification::STATUS_SENDING, $notification->getStatus());
        
        $notification->setStatus(Notification::STATUS_SENT);
        $this->assertTrue($notification->isSent());
        $this->assertFalse($notification->isQueued());
        
        $notification->setStatus(Notification::STATUS_FAILED);
        $this->assertTrue($notification->isFailed());
        $this->assertFalse($notification->isSent());
        
        $notification->setStatus(Notification::STATUS_CANCELLED);
        $this->assertTrue($notification->isCancelled());
        $this->assertFalse($notification->isFailed());
    }

    public function testMessageSchedulingOverrides(): void
    {
        // Create notification with individual message scheduling
        $notification = new Notification();
        $notification->setType('override_test');
        $notification->setSubject('Override Test');
        $notification->setContent('Individual scheduling test');
        $notification->setChannels(['email', 'sms']);
        $notification->setRecipients([
            [
                'email' => 'email@example.com',
                'channel' => 'email',
                'scheduledAt' => '2025-09-22T09:00:00Z'
            ],
            [
                'phone' => '+1234567890',
                'channel' => 'sms', 
                'scheduledAt' => '2025-09-22T14:00:00Z'
            ]
        ]);
        $notification->setStatus(Notification::STATUS_DRAFT);
        
        $this->entityManager->persist($notification);
        $this->entityManager->flush();
        
        // Test the notification structure
        $this->assertEquals(['email', 'sms'], $notification->getChannels());
        $this->assertCount(2, $notification->getRecipients());
        
        $recipients = $notification->getRecipients();
        $this->assertEquals('email@example.com', $recipients[0]['email']);
        $this->assertEquals('2025-09-22T09:00:00Z', $recipients[0]['scheduledAt']);
        $this->assertEquals('+1234567890', $recipients[1]['phone']);
        $this->assertEquals('2025-09-22T14:00:00Z', $recipients[1]['scheduledAt']);
    }

    public function testNotificationMetadata(): void
    {
        $notification = new Notification();
        $metadata = [
            'source' => 'integration_test',
            'campaign_id' => 'test_campaign_123',
            'html_content' => '<h1>Test HTML</h1>',
            'slack_blocks' => [
                ['type' => 'section', 'text' => ['type' => 'plain_text', 'text' => 'Test']]
            ]
        ];
        
        $notification->setMetadata($metadata);
        
        $this->assertEquals($metadata, $notification->getMetadata());
        $this->assertEquals('integration_test', $notification->getMetadata()['source']);
        $this->assertEquals('test_campaign_123', $notification->getMetadata()['campaign_id']);
        $this->assertArrayHasKey('html_content', $notification->getMetadata());
        $this->assertArrayHasKey('slack_blocks', $notification->getMetadata());
    }

    public function testNotificationDirectionConstants(): void
    {
        $this->assertEquals([
            'inbound',
            'outbound',
            'draft'
        ], Notification::ALLOWED_DIRECTIONS);
        
        $this->assertEquals([
            'draft',
            'scheduled',
            'queued',
            'sending',
            'sent',
            'failed',
            'cancelled'
        ], Notification::ALLOWED_STATUSES);
    }

    protected function tearDown(): void
    {
        // Clean up test data
        $this->entityManager->createQuery('DELETE FROM ' . Notification::class . ' n WHERE n.type LIKE :type')
            ->setParameter('type', '%test%')
            ->execute();
            
        parent::tearDown();
    }
}
