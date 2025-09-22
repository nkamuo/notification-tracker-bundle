<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Nkamuo\NotificationTrackerBundle\Entity\Notification;
use Nkamuo\NotificationTrackerBundle\Message\SendNotificationMessage;
use Nkamuo\NotificationTrackerBundle\Service\NotificationSender;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Uid\Ulid;

class NotificationSenderTest extends TestCase
{
    /** @var EntityManagerInterface&MockObject */
    private MockObject $entityManager;
    /** @var MessageBusInterface&MockObject */
    private MockObject $messageBus;
    /** @var LoggerInterface&MockObject */
    private MockObject $logger;
    private NotificationSender $notificationSender;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        
        $this->notificationSender = new NotificationSender(
            $this->entityManager,
            $this->messageBus,
            $this->logger
        );
    }

    public function testSendNotificationImmediately(): void
    {
        $notification = new Notification();
        $notification->setStatus(Notification::STATUS_DRAFT);
        
        $this->entityManager->expects($this->once())
            ->method('flush');
            
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->isInstanceOf(SendNotificationMessage::class),
                $this->equalTo([])  // No DelayStamp for immediate delivery
            );
            
        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'Notification dispatched immediately',
                $this->arrayHasKey('notification_id')
            );

        $result = $this->notificationSender->sendNotification($notification);

        $this->assertTrue($result['success']);
        $this->assertEquals(Notification::STATUS_QUEUED, $notification->getStatus());
        $this->assertFalse($result['scheduled']);
        $this->assertEquals(0, $result['delay_ms']);
    }

    public function testSendNotificationWithScheduling(): void
    {
        $notification = new Notification();
        $notification->setStatus(Notification::STATUS_DRAFT);
        $notification->setScheduledAt(new \DateTimeImmutable('+1 hour'));
        
        $this->entityManager->expects($this->once())
            ->method('flush');
            
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->isInstanceOf(SendNotificationMessage::class),
                $this->callback(function ($stamps) {
                    return count($stamps) === 1 && $stamps[0] instanceof DelayStamp;
                })
            );
            
        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'Notification scheduled for later delivery',
                $this->logicalAnd(
                    $this->arrayHasKey('notification_id'),
                    $this->arrayHasKey('delay_ms'),
                    $this->arrayHasKey('scheduled_at')
                )
            );

        $result = $this->notificationSender->sendNotification($notification);

        $this->assertTrue($result['success']);
        $this->assertEquals(Notification::STATUS_SCHEDULED, $notification->getStatus());
        $this->assertTrue($result['scheduled']);
        $this->assertGreaterThan(0, $result['delay_ms']);
    }

    public function testSendNotificationWithInvalidStatus(): void
    {
        $notification = new Notification();
        $notification->setStatus(Notification::STATUS_SENT); // Invalid status
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Notification must be in draft, scheduled, or queued status to send');
        
        $this->notificationSender->sendNotification($notification);
    }

    public function testSendNotificationToSpecificChannels(): void
    {
        $notification = new Notification();
        $notification->setStatus(Notification::STATUS_DRAFT);
        
        $channels = ['email', 'sms'];
        $recipientOverrides = [
            ['email' => 'test@example.com', 'channel' => 'email']
        ];
        
        $this->entityManager->expects($this->once())
            ->method('flush');
            
        $this->messageBus->expects($this->exactly(2))  // One for each channel
            ->method('dispatch')
            ->with($this->isInstanceOf(SendNotificationMessage::class));

        $result = $this->notificationSender->sendNotificationToChannels(
            $notification,
            $channels,
            $recipientOverrides
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(Notification::STATUS_QUEUED, $notification->getStatus());
        $this->assertCount(2, $result['channels']);
        $this->assertEquals(2, $result['summary']['total_channels']);
        $this->assertEquals(2, $result['summary']['queued_channels']);
    }

    public function testSendNotificationWithException(): void
    {
        $notification = new Notification();
        $notification->setStatus(Notification::STATUS_DRAFT);
        
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willThrowException(new \Exception('Messenger error'));
            
        $this->entityManager->expects($this->once())
            ->method('flush');
            
        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'Failed to send notification',
                $this->logicalAnd(
                    $this->arrayHasKey('notification_id'),
                    $this->arrayHasKey('error')
                )
            );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Messenger error');

        $this->notificationSender->sendNotification($notification);
        
        $this->assertEquals(Notification::STATUS_FAILED, $notification->getStatus());
    }

    public function testCalculateNotificationDelayWithPastScheduling(): void
    {
        $notification = new Notification();
        $notification->setStatus(Notification::STATUS_DRAFT);
        $notification->setScheduledAt(new \DateTimeImmutable('-1 hour')); // Past time
        
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->isInstanceOf(SendNotificationMessage::class),
                $this->equalTo([])  // No DelayStamp for past scheduling
            );

        $result = $this->notificationSender->sendNotification($notification);

        $this->assertFalse($result['scheduled']);
        $this->assertEquals(0, $result['delay_ms']);
        $this->assertEquals(Notification::STATUS_QUEUED, $notification->getStatus());
    }
}
