<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Tests\Unit\Entity;

use Nkamuo\NotificationTrackerBundle\Entity\Message;
use Nkamuo\NotificationTrackerBundle\Entity\EmailMessage;
use Nkamuo\NotificationTrackerBundle\Entity\Notification;
use PHPUnit\Framework\TestCase;

class MessageTest extends TestCase
{
    public function testMessageCreation(): void
    {
        $message = new EmailMessage();
        
        $this->assertInstanceOf(Message::class, $message);
        $this->assertEquals(Message::STATUS_PENDING, $message->getStatus());
        $this->assertEquals(Message::DIRECTION_OUTBOUND, $message->getDirection());
        $this->assertInstanceOf(\DateTimeImmutable::class, $message->getCreatedAt());
        $this->assertNull($message->getScheduledAt());
        $this->assertNull($message->getSentAt());
        $this->assertFalse($message->getHasScheduleOverride());
    }

    public function testMessageSchedulingOverride(): void
    {
        $message = new EmailMessage();
        $notification = new Notification();
        $message->setNotification($notification);
        
        // Test notification-level scheduling
        $notificationScheduled = new \DateTimeImmutable('+1 hour');
        $notification->setScheduledAt($notificationScheduled);
        
        $this->assertEquals($notificationScheduled, $message->getEffectiveScheduledAt());
        $this->assertFalse($message->getHasScheduleOverride());
        
        // Test message-level override
        $messageScheduled = new \DateTimeImmutable('+2 hours');
        $message->scheduleFor($messageScheduled, true);
        
        $this->assertEquals($messageScheduled, $message->getScheduledAt());
        $this->assertTrue($message->getHasScheduleOverride());
        $this->assertEquals($messageScheduled, $message->getEffectiveScheduledAt());
    }

    public function testMessageReadyToSend(): void
    {
        $message = new EmailMessage();
        $now = new \DateTimeImmutable();
        
        // No scheduling - ready immediately
        $this->assertTrue($message->isReadyToSend($now));
        
        // Scheduled for future - not ready
        $future = new \DateTimeImmutable('+1 hour');
        $message->scheduleFor($future, true);
        $this->assertFalse($message->isReadyToSend($now));
        
        // Scheduled for past - ready
        $past = new \DateTimeImmutable('-1 hour');
        $message->scheduleFor($past, true);
        $this->assertTrue($message->isReadyToSend($now));
    }

    public function testMessageWithNotificationScheduling(): void
    {
        $message = new EmailMessage();
        $notification = new Notification();
        $message->setNotification($notification);
        
        $now = new \DateTimeImmutable();
        $future = new \DateTimeImmutable('+1 hour');
        
        // Notification scheduled for future
        $notification->setScheduledAt($future);
        $this->assertFalse($message->isReadyToSend($now));
        $this->assertEquals($future, $message->getEffectiveScheduledAt());
        
        // Message override with earlier time
        $earlierTime = new \DateTimeImmutable('+30 minutes');
        $message->scheduleFor($earlierTime, true);
        $this->assertFalse($message->isReadyToSend($now));
        $this->assertEquals($earlierTime, $message->getEffectiveScheduledAt());
        
        // Message override with past time (ready now)
        $pastTime = new \DateTimeImmutable('-30 minutes');
        $message->scheduleFor($pastTime, true);
        $this->assertTrue($message->isReadyToSend($now));
        $this->assertEquals($pastTime, $message->getEffectiveScheduledAt());
    }

    public function testMessageDirectionConstants(): void
    {
        $expectedDirections = [
            'outbound',
            'inbound',
            'draft'
        ];
        
        $this->assertEquals($expectedDirections, Message::ALLOWED_DIRECTIONS);
        
        // Test setting directions
        $message = new EmailMessage();
        
        $message->setDirection(Message::DIRECTION_OUTBOUND);
        $this->assertEquals(Message::DIRECTION_OUTBOUND, $message->getDirection());
        
        $message->setDirection(Message::DIRECTION_INBOUND);
        $this->assertEquals(Message::DIRECTION_INBOUND, $message->getDirection());
        
        $message->setDirection(Message::DIRECTION_DRAFT);
        $this->assertEquals(Message::DIRECTION_DRAFT, $message->getDirection());
    }

    public function testMessageStatusConstants(): void
    {
        $expectedStatuses = [
            'pending',
            'queued',
            'sending',
            'sent',
            'delivered',
            'failed',
            'bounced',
            'cancelled',
            'retrying'
        ];
        
        $message = new EmailMessage();
        
        // Test status transitions
        $message->setStatus(Message::STATUS_PENDING);
        $this->assertEquals(Message::STATUS_PENDING, $message->getStatus());
        
        $message->setStatus(Message::STATUS_QUEUED);
        $this->assertEquals(Message::STATUS_QUEUED, $message->getStatus());
        
        $message->setStatus(Message::STATUS_SENDING);
        $this->assertEquals(Message::STATUS_SENDING, $message->getStatus());
        
        $message->setStatus(Message::STATUS_SENT);
        $this->assertEquals(Message::STATUS_SENT, $message->getStatus());
        
        $message->setStatus(Message::STATUS_DELIVERED);
        $this->assertEquals(Message::STATUS_DELIVERED, $message->getStatus());
        
        $message->setStatus(Message::STATUS_FAILED);
        $this->assertEquals(Message::STATUS_FAILED, $message->getStatus());
    }

    public function testMessageMetadata(): void
    {
        $message = new EmailMessage();
        
        $metadata = [
            'provider' => 'sendgrid',
            'template_id' => 'template_123',
            'tracking_enabled' => true
        ];
        
        $message->setMetadata($metadata);
        $this->assertEquals($metadata, $message->getMetadata());
        
        // Test metadata access
        $this->assertEquals('sendgrid', $message->getMetadata()['provider'] ?? null);
        $this->assertTrue($message->getMetadata()['tracking_enabled'] ?? false);
    }

    public function testMessageFailureHandling(): void
    {
        $message = new EmailMessage();
        
        $message->setStatus(Message::STATUS_FAILED);
        $message->setFailureReason('SMTP connection failed');
        $message->setRetryCount(2);
        
        $this->assertEquals(Message::STATUS_FAILED, $message->getStatus());
        $this->assertEquals('SMTP connection failed', $message->getFailureReason());
        $this->assertEquals(2, $message->getRetryCount());
    }

    public function testMessageSchedulingWithoutNotification(): void
    {
        $message = new EmailMessage();
        $now = new \DateTimeImmutable();
        
        // No notification, no scheduling
        $this->assertNull($message->getEffectiveScheduledAt());
        $this->assertTrue($message->isReadyToSend($now));
        
        // Message has its own scheduling
        $future = new \DateTimeImmutable('+1 hour');
        $message->scheduleFor($future, false); // Not an override
        
        $this->assertEquals($future, $message->getEffectiveScheduledAt());
        $this->assertFalse($message->isReadyToSend($now));
        $this->assertFalse($message->getHasScheduleOverride());
    }
}
