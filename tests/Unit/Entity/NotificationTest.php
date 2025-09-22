<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Tests\Unit\Entity;

use Nkamuo\NotificationTrackerBundle\Entity\Notification;
use Nkamuo\NotificationTrackerBundle\Entity\Message;
use Nkamuo\NotificationTrackerBundle\Entity\EmailMessage;
use PHPUnit\Framework\TestCase;

class NotificationTest extends TestCase
{
    public function testNotificationCreation(): void
    {
        $notification = new Notification();
        
        $this->assertInstanceOf(Notification::class, $notification);
        $this->assertEquals(Notification::STATUS_DRAFT, $notification->getStatus());
        $this->assertEquals(Notification::DIRECTION_DRAFT, $notification->getDirection());
        $this->assertInstanceOf(\DateTimeImmutable::class, $notification->getCreatedAt());
        $this->assertNull($notification->getScheduledAt());
        $this->assertNull($notification->getSentAt());
        $this->assertEmpty($notification->getMetadata());
    }

    public function testNotificationStatusWorkflow(): void
    {
        $notification = new Notification();
        
        // Draft → Scheduled
        $scheduledAt = new \DateTimeImmutable('+1 hour');
        $notification->setStatus(Notification::STATUS_SCHEDULED);
        $notification->setScheduledAt($scheduledAt);
        
        $this->assertEquals(Notification::STATUS_SCHEDULED, $notification->getStatus());
        $this->assertEquals($scheduledAt, $notification->getScheduledAt());
        $this->assertTrue($notification->isScheduled());
        $this->assertFalse($notification->isDraft());
        
        // Scheduled → Queued
        $notification->setStatus(Notification::STATUS_QUEUED);
        $this->assertEquals(Notification::STATUS_QUEUED, $notification->getStatus());
        
        // Queued → Sending
        $notification->setStatus(Notification::STATUS_SENDING);
        $this->assertEquals(Notification::STATUS_SENDING, $notification->getStatus());
        
        // Sending → Sent
        $notification->setStatus(Notification::STATUS_SENT);
        $notification->setDirection(Notification::DIRECTION_OUTBOUND);
        $notification->setSentAt(new \DateTimeImmutable());
        
        $this->assertEquals(Notification::STATUS_SENT, $notification->getStatus());
        $this->assertEquals(Notification::DIRECTION_OUTBOUND, $notification->getDirection());
        $this->assertInstanceOf(\DateTimeImmutable::class, $notification->getSentAt());
    }

    public function testNotificationConvenienceMethods(): void
    {
        $notification = new Notification();
        
        // Test isDraft
        $notification->setStatus(Notification::STATUS_DRAFT);
        $this->assertTrue($notification->isDraft());
        $this->assertFalse($notification->isScheduled());
        
        // Test isScheduled
        $notification->setStatus(Notification::STATUS_SCHEDULED);
        $this->assertTrue($notification->isScheduled());
        $this->assertFalse($notification->isDraft());
        
        // Test isQueued
        $notification->setStatus(Notification::STATUS_QUEUED);
        $this->assertTrue($notification->isQueued());
        $this->assertFalse($notification->isScheduled());
        
        // Test isSent
        $notification->setStatus(Notification::STATUS_SENT);
        $this->assertTrue($notification->isSent());
        $this->assertFalse($notification->isQueued());
        
        // Test isFailed
        $notification->setStatus(Notification::STATUS_FAILED);
        $this->assertTrue($notification->isFailed());
        $this->assertFalse($notification->isSent());
        
        // Test isCancelled
        $notification->setStatus(Notification::STATUS_CANCELLED);
        $this->assertTrue($notification->isCancelled());
        $this->assertFalse($notification->isFailed());
    }

    public function testNotificationMetadata(): void
    {
        $notification = new Notification();
        
        $metadata = [
            'source' => 'user',
            'campaign_id' => 'test_campaign',
            'html_content' => '<h1>Test</h1>',
            'slack_blocks' => [['type' => 'section']]
        ];
        
        $notification->setMetadata($metadata);
        $this->assertEquals($metadata, $notification->getMetadata());
        
        // Test metadata access
        $this->assertEquals('user', $notification->getMetadata()['source'] ?? null);
        $this->assertEquals('test_campaign', $notification->getMetadata()['campaign_id'] ?? null);
    }

    public function testNotificationChannelsAndRecipients(): void
    {
        $notification = new Notification();
        
        $channels = ['email', 'sms', 'slack'];
        $recipients = [
            ['email' => 'test@example.com', 'channel' => 'email'],
            ['phone' => '+1234567890', 'channel' => 'sms']
        ];
        
        $notification->setChannels($channels);
        $notification->setRecipients($recipients);
        
        $this->assertEquals($channels, $notification->getChannels());
        $this->assertEquals($recipients, $notification->getRecipients());
    }

    public function testNotificationScheduling(): void
    {
        $notification = new Notification();
        $future = new \DateTimeImmutable('+2 hours');
        $past = new \DateTimeImmutable('-1 hour');
        
        // Test future scheduling
        $notification->setScheduledAt($future);
        $this->assertEquals($future, $notification->getScheduledAt());
        
        // Test past scheduling (should still be allowed)
        $notification->setScheduledAt($past);
        $this->assertEquals($past, $notification->getScheduledAt());
        
        // Test null scheduling
        $notification->setScheduledAt(null);
        $this->assertNull($notification->getScheduledAt());
    }

    public function testNotificationWithMessages(): void
    {
        $notification = new Notification();
        $emailMessage = new EmailMessage();
        $emailMessage->setNotification($notification);
        
        $notification->addMessage($emailMessage);
        
        $this->assertCount(1, $notification->getMessages());
        $this->assertSame($emailMessage, $notification->getMessages()->first());
        $this->assertSame($notification, $emailMessage->getNotification());
        
        // Test remove message
        $notification->removeMessage($emailMessage);
        $this->assertCount(0, $notification->getMessages());
    }

    public function testStatusConstants(): void
    {
        $expectedStatuses = [
            'draft',
            'scheduled', 
            'queued',
            'sending',
            'sent',
            'failed',
            'cancelled'
        ];
        
        $this->assertEquals($expectedStatuses, Notification::ALLOWED_STATUSES);
        
        $expectedDirections = [
            'inbound',
            'outbound', 
            'draft'
        ];
        
        $this->assertEquals($expectedDirections, Notification::ALLOWED_DIRECTIONS);
    }
}
