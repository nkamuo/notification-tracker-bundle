<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Nkamuo\NotificationTrackerBundle\Entity\MessageEvent;
use Nkamuo\NotificationTrackerBundle\Entity\WebhookPayload;
use Nkamuo\NotificationTrackerBundle\Message\ProcessWebhookMessage;
use Nkamuo\NotificationTrackerBundle\Webhook\Provider\WebhookProviderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class WebhookProcessor
{
    /** @var WebhookProviderInterface[] */
    private array $providers = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageTracker $messageTracker,
        private readonly LoggerInterface $logger,
        private readonly MessageBusInterface $messageBus,
        private readonly bool $asyncProcessing = true,
        private readonly bool $verifySignatures = true
    ) {
    }

    public function addProvider(WebhookProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    public function processWebhook(
        string $provider,
        array $payload,
        array $headers = []
    ): WebhookPayload {
        $webhookPayload = new WebhookPayload();
        $webhookPayload->setProvider($provider);
        $webhookPayload->setRawPayload($payload);
        $webhookPayload->setSignature($headers['signature'] ?? null);

        $this->entityManager->persist($webhookPayload);
        $this->entityManager->flush();

        if ($this->asyncProcessing) {
            // Process async via messenger
            $this->messageBus->dispatch(new ProcessWebhookMessage(
                $webhookPayload->getId(),
                $provider,
                $payload,
                $headers
            ));
        } else {
            // Process synchronously
            $this->processWebhookPayload($webhookPayload, $provider, $payload, $headers);
        }

        return $webhookPayload;
    }

    public function processWebhookPayload(
        WebhookPayload $webhookPayload,
        string $provider,
        array $payload,
        array $headers
    ): void {
        try {
            $providerHandler = $this->getProviderHandler($provider);
            
            // Verify webhook signature
            if ($this->verifySignatures && !$providerHandler->verifySignature($payload, $headers)) {
                throw new \Exception('Invalid webhook signature');
            }

            // Parse webhook data
            $parsedData = $providerHandler->parseWebhook($payload);
            
            if (isset($parsedData['events'])) {
                // Handle batch events
                foreach ($parsedData['events'] as $eventData) {
                    $this->processEvent($eventData, $provider, $webhookPayload);
                }
            } else {
                // Handle single event
                $this->processEvent($parsedData, $provider, $webhookPayload);
            }

            $webhookPayload->setProcessed(true);
            $webhookPayload->setEventType($parsedData['event_type'] ?? 'unknown');
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to process webhook', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);
            
            $webhookPayload->setProcessed(false);
            $webhookPayload->setProcessingError($e->getMessage());
        }

        $this->entityManager->flush();
    }

    private function processEvent(array $eventData, string $provider, WebhookPayload $webhookPayload): void
    {
        if (!isset($eventData['message_id'])) {
            $this->logger->warning('No message ID in webhook event', [
                'provider' => $provider,
            ]);
            return;
        }

        // Find the message
        $message = $this->messageTracker->findByProviderMessageId(
            $eventData['message_id'],
            $provider
        );

        if (!$message) {
            $this->logger->warning('Message not found for webhook', [
                'provider' => $provider,
                'message_id' => $eventData['message_id'],
            ]);
            return;
        }

        // Find recipient if applicable
        $recipient = null;
        if (isset($eventData['recipient_email'])) {
            foreach ($message->getRecipients() as $r) {
                if ($r->getAddress() === $eventData['recipient_email']) {
                    $recipient = $r;
                    break;
                }
            }
        }

        // Add event to message
        $this->messageTracker->addEvent(
            $message,
            $eventData['event_type'],
            $eventData['event_data'] ?? [],
            $recipient,
            $webhookPayload
        );
    }

    private function getProviderHandler(string $provider): WebhookProviderInterface
    {
        foreach ($this->providers as $handler) {
            if ($handler->supports($provider)) {
                return $handler;
            }
        }

        throw new \InvalidArgumentException("No webhook handler found for provider: {$provider}");
    }
}