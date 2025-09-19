<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\MessageHandler;

use Nkamuo\NotificationTrackerBundle\Message\ProcessWebhookMessage;
use Nkamuo\NotificationTrackerBundle\Service\WebhookProcessor;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ProcessWebhookMessageHandler
{
    public function __construct(
        private readonly WebhookProcessor $webhookProcessor,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(ProcessWebhookMessage $message): void
    {
        try {
            $this->logger->info('Processing webhook message', [
                'webhook_id' => $message->getWebhookId()->toRfc4122(),
                'provider' => $message->getProvider(),
            ]);

            $this->webhookProcessor->processWebhook(
                $message->getProvider(),
                $message->getPayload(),
                $message->getHeaders()
            );

            $this->logger->info('Webhook message processed successfully', [
                'webhook_id' => $message->getWebhookId()->toRfc4122(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to process webhook message', [
                'webhook_id' => $message->getWebhookId()->toRfc4122(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
