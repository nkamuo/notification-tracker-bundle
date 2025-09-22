<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Tests\Unit\MessageHandler;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Nkamuo\NotificationTrackerBundle\Entity\Notification;
use Nkamuo\NotificationTrackerBundle\Entity\EmailMessage;
use Nkamuo\NotificationTrackerBundle\Message\SendNotificationMessage;
use Nkamuo\NotificationTrackerBundle\Message\SendChannelMessage;
use Nkamuo\NotificationTrackerBundle\MessageHandler\SendNotificationMessageHandler;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Uid\Ulid;

class SendNotificationMessageHandlerTest extends TestCase
{
    /** @var EntityManagerInterface&MockObject */
    private MockObject $entityManager;
    /** @var MessageBusInterface&MockObject */
    private MockObject $messageBus;
    /** @var LoggerInterface&MockObject */
    private MockObject $logger;
    /** @var EntityRepository&MockObject */
    private MockObject $notificationRepository;
    /** @var EntityRepository&MockObject */
    private MockObject $messageRepository;
    private SendNotificationMessageHandler $handler;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->notificationRepository = $this->createMock(EntityRepository::class);
        $this->messageRepository = $this->createMock(EntityRepository::class);
        
        $this->entityManager->expects($this->any())
            ->method('getRepository')
            ->willReturnMap([
                [Notification::class, $this->notificationRepository],
                [EmailMessage::class, $this->messageRepository]
            ]);
        
        $this->handler = new SendNotificationMessageHandler(
            $this->entityManager,
            $this->messageBus,
            $this->logger
        );
    }

    public function testHandleNotificationNotFound(): void
    {
        $notificationId = new Ulid();
        $message = new SendNotificationMessage($notificationId);
        
        $this->notificationRepository->expects($this->once())
            ->method('find')
            ->with($notificationId)
            ->willReturn(null);
            
        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'Notification not found',
                ['notification_id' => (string) $notificationId]
            );

        $this->handler->__invoke($message);
    }

    public function testHandleNotificationInvalidStatus(): void
    {
        $notification = new Notification();
        $notification->setStatus(Notification::STATUS_SENT); // Invalid status
        
        $message = new SendNotificationMessage($notification->getId());
        
        $this->notificationRepository->expects($this->once())
            ->method('find')
            ->willReturn($notification);
            
        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                'Notification not in sendable status',
                [
                    'notification_id' => (string) $notification->getId(),
                    'status' => Notification::STATUS_SENT
                ]
            );

        $this->handler->__invoke($message);
    }

    public function testHandleNotificationSuccess(): void
    {
        $notification = new Notification();
        $notification->setStatus(Notification::STATUS_DRAFT);
        $notification->setChannels(['email']);
        $notification->setRecipients([
            ['email' => 'test@example.com', 'channel' => 'email']
        ]);
        
        $message = new SendNotificationMessage($notification->getId());
        
        $this->notificationRepository->expects($this->once())
            ->method('find')
            ->willReturn($notification);
            
        $this->entityManager->expects($this->atLeastOnce())
            ->method('persist');
            
        $this->entityManager->expects($this->atLeastOnce())
            ->method('flush');
            
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(SendChannelMessage::class));
            
        // Mock message count query
        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\AbstractQuery::class);
        
        $queryBuilder->expects($this->any())
            ->method('select')
            ->willReturnSelf();
        $queryBuilder->expects($this->any())
            ->method('where')
            ->willReturnSelf();
        $queryBuilder->expects($this->any())
            ->method('setParameter')
            ->willReturnSelf();
        $queryBuilder->expects($this->any())
            ->method('getQuery')
            ->willReturn($query);
            
        $query->expects($this->once())
            ->method('getSingleScalarResult')
            ->willReturn(1); // One message created
            
        $this->messageRepository->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);
            
        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'Notification processed for sending',
                $this->arrayHasKey('notification_id')
            );

        $this->handler->__invoke($message);
        
        $this->assertEquals(Notification::STATUS_QUEUED, $notification->getStatus());
        $this->assertEquals(Notification::DIRECTION_OUTBOUND, $notification->getDirection());
    }

    public function testHandleNotificationWithScheduling(): void
    {
        $notification = new Notification();
        $notification->setStatus(Notification::STATUS_DRAFT);
        $notification->setChannels(['email']);
        $notification->setRecipients([
            [
                'email' => 'test@example.com', 
                'channel' => 'email',
                'scheduledAt' => '2025-09-22T15:00:00Z'
            ]
        ]);
        
        $message = new SendNotificationMessage($notification->getId());
        
        $this->notificationRepository->expects($this->once())
            ->method('find')
            ->willReturn($notification);
            
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->isInstanceOf(SendChannelMessage::class),
                $this->callback(function ($stamps) {
                    return count($stamps) === 1 && $stamps[0] instanceof DelayStamp;
                })
            );

        // Mock the rest of the dependencies
        $this->setupMockQueryBuilder();

        $this->handler->__invoke($message);
    }

    public function testHandleNotificationWithSpecificChannel(): void
    {
        $notification = new Notification();
        $notification->setStatus(Notification::STATUS_DRAFT);
        $notification->setChannels(['email', 'sms']);
        $notification->setRecipients([
            ['email' => 'test@example.com', 'channel' => 'email'],
            ['phone' => '+1234567890', 'channel' => 'sms']
        ]);
        
        // Message specifying only email channel
        $message = new SendNotificationMessage($notification->getId(), 'email');
        
        $this->notificationRepository->expects($this->once())
            ->method('find')
            ->willReturn($notification);
            
        // Should only dispatch one message for email channel
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(SendChannelMessage::class));

        $this->setupMockQueryBuilder();

        $this->handler->__invoke($message);
    }

    public function testHandleNotificationWithRecipientOverrides(): void
    {
        $notification = new Notification();
        $notification->setStatus(Notification::STATUS_DRAFT);
        $notification->setChannels(['email']);
        $notification->setRecipients([
            ['email' => 'original@example.com', 'channel' => 'email']
        ]);
        
        $recipientOverrides = [
            ['email' => 'override@example.com', 'channel' => 'email']
        ];
        
        $message = new SendNotificationMessage(
            $notification->getId(),
            null,
            $recipientOverrides
        );
        
        $this->notificationRepository->expects($this->once())
            ->method('find')
            ->willReturn($notification);
            
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(SendChannelMessage::class));

        $this->setupMockQueryBuilder();

        $this->handler->__invoke($message);
    }

    public function testHandleNotificationWithException(): void
    {
        $notification = new Notification();
        $notification->setStatus(Notification::STATUS_DRAFT);
        $notification->setChannels(['email']);
        $notification->setRecipients([
            ['email' => 'test@example.com', 'channel' => 'email']
        ]);
        
        $message = new SendNotificationMessage($notification->getId());
        
        $this->notificationRepository->expects($this->once())
            ->method('find')
            ->willReturn($notification);
            
        $this->entityManager->expects($this->any())
            ->method('persist')
            ->willThrowException(new \Exception('Database error'));
            
        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'Failed to process notification',
                $this->arrayHasKey('error')
            );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database error');

        $this->handler->__invoke($message);
        
        $this->assertEquals(Notification::STATUS_FAILED, $notification->getStatus());
    }

    private function setupMockQueryBuilder(): void
    {
        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\AbstractQuery::class);
        
        $queryBuilder->expects($this->any())
            ->method('select')
            ->willReturnSelf();
        $queryBuilder->expects($this->any())
            ->method('where')
            ->willReturnSelf();
        $queryBuilder->expects($this->any())
            ->method('setParameter')
            ->willReturnSelf();
        $queryBuilder->expects($this->any())
            ->method('getQuery')
            ->willReturn($query);
            
        $query->expects($this->any())
            ->method('getSingleScalarResult')
            ->willReturn(1);
            
        $this->messageRepository->expects($this->any())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);
            
        $this->entityManager->expects($this->any())
            ->method('persist');
            
        $this->entityManager->expects($this->any())
            ->method('flush');
    }
}
