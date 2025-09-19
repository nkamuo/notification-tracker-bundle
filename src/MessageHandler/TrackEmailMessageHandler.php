<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\MessageHandler;

use Nkamuo\NotificationTrackerBundle\Message\TrackEmailMessage;
use Nkamuo\NotificationTrackerBundle\Service\MessageTracker;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class TrackEmailMessageHandler
{
    public function __construct(
        private readonly MessageTracker $messageTracker,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(TrackEmailMessage $message): void
    {
        try {
            $this->logger->info('Tracking email message event', [
                'message_id' => $message->getMessageId()->toRfc4122(),
                'event' => $message->getEvent(),
            ]);

            // Find the message by ID
            $messageEntity = $this->messageTracker->findById($message->getMessageId()->toRfc4122());
            
            if (!$messageEntity) {
                $this->logger->warning('Message not found for tracking event', [
                    'message_id' => $message->getMessageId()->toRfc4122(),
                ]);
                return;
            }

            $this->messageTracker->addEvent(
                $messageEntity,
                $message->getEvent(),
                $message->getData()
            );

            $this->logger->info('Email message event tracked successfully', [
                'message_id' => $message->getMessageId()->toRfc4122(),
                'event' => $message->getEvent(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to track email message event', [
                'message_id' => $message->getMessageId()->toRfc4122(),
                'event' => $message->getEvent(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
