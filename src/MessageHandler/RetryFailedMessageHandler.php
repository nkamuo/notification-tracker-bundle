<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\MessageHandler;

use Nkamuo\NotificationTrackerBundle\Message\RetryFailedMessage;
use Nkamuo\NotificationTrackerBundle\Service\MessageRetryService;
use Nkamuo\NotificationTrackerBundle\Service\MessageTracker;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class RetryFailedMessageHandler
{
    public function __construct(
        private readonly MessageRetryService $messageRetryService,
        private readonly MessageTracker $messageTracker,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(RetryFailedMessage $message): void
    {
        try {
            $this->logger->info('Retrying failed message', [
                'message_id' => $message->getMessageId()->toRfc4122(),
                'attempt' => $message->getAttempt(),
            ]);

            // Find the message entity
            $messageEntity = $this->messageTracker->findById($message->getMessageId()->toRfc4122());
            
            if (!$messageEntity) {
                $this->logger->warning('Message not found for retry', [
                    'message_id' => $message->getMessageId()->toRfc4122(),
                ]);
                return;
            }

            $success = $this->messageRetryService->retryMessage($messageEntity);

            if ($success) {
                $this->logger->info('Failed message retry completed successfully', [
                    'message_id' => $message->getMessageId()->toRfc4122(),
                    'attempt' => $message->getAttempt(),
                ]);
            } else {
                $this->logger->warning('Failed message retry was not successful', [
                    'message_id' => $message->getMessageId()->toRfc4122(),
                    'attempt' => $message->getAttempt(),
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to retry message', [
                'message_id' => $message->getMessageId()->toRfc4122(),
                'attempt' => $message->getAttempt(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
