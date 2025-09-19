<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Service;

use Nkamuo\NotificationTrackerBundle\Entity\Message;
use Nkamuo\NotificationTrackerBundle\Entity\MessageEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Doctrine\ORM\EntityManagerInterface;

class MessageRetryService
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageTracker $messageTracker,
        private readonly LoggerInterface $logger
    ) {
    }

    public function retryMessage(Message $message): bool
    {
        if ($message->getStatus() !== Message::STATUS_FAILED) {
            $this->logger->warning('Cannot retry non-failed message', [
                'message_id' => (string) $message->getId(),
                'status' => $message->getStatus(),
            ]);
            return false;
        }

        try {
            $message->setStatus(Message::STATUS_RETRYING);
            $message->incrementRetryCount();
            
            $this->messageTracker->addEvent(
                $message,
                MessageEvent::TYPE_RETRIED,
                [
                    'retry_count' => $message->getRetryCount(),
                    'previous_error' => $message->getFailureReason(),
                ]
            );

            // Here you would dispatch the appropriate message to retry sending
            // This depends on your implementation
            
            $this->entityManager->flush();
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to retry message', [
                'message_id' => (string) $message->getId(),
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}