<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Nkamuo\NotificationTrackerBundle\Entity\MessageEvent;
use Nkamuo\NotificationTrackerBundle\Entity\WebhookPayload;
use Nkamuo\NotificationTrackerBundle\Entity\WebhookEndpoint;
use Nkamuo\NotificationTrackerBundle\Entity\Message;
use Nkamuo\NotificationTrackerBundle\Message\ProcessWebhookMessage;
use Nkamuo\NotificationTrackerBundle\Webhook\Provider\WebhookProviderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Ulid;

class WebhookProcessor
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageTracker $messageTracker,
        private readonly NotificationTracker $notificationTracker,
        private readonly WebhookProviderRegistry $providerRegistry,
        private readonly LoggerInterface $logger,
        private readonly MessageBusInterface $messageBus,
        private readonly bool $asyncProcessing = true,
        private readonly bool $verifySignatures = true
    ) {
    }

    public function processWebhook(
        string $provider,
        array $payload,
        array $headers = [],
        ?string $endpointId = null
    ): WebhookPayload {
        $webhookEndpoint = null;
        
        // Find webhook endpoint if ID provided
        if ($endpointId) {
            $webhookEndpoint = $this->entityManager
                ->getRepository(WebhookEndpoint::class)
                ->find(Ulid::fromString($endpointId));
        }

        $webhookPayload = new WebhookPayload();
        $webhookPayload->setProvider($provider);
        $webhookPayload->setRawPayload($payload);
        $webhookPayload->setSignature($headers['signature'] ?? null);

        $this->entityManager->persist($webhookPayload);
        
        // Update endpoint stats if found
        if ($webhookEndpoint) {
            $webhookEndpoint->incrementTotalRequests();
            $webhookEndpoint->setLastUsedAt(new \DateTimeImmutable());
        }

        $this->entityManager->flush();

        if ($this->asyncProcessing) {
            // Process async via messenger
            $this->messageBus->dispatch(new ProcessWebhookMessage(
                $webhookPayload->getId(),
                $provider,
                $payload,
                $headers,
                $endpointId
            ));
        } else {
            // Process synchronously
            $this->processWebhookPayload($webhookPayload, $provider, $payload, $headers, $webhookEndpoint);
        }

        return $webhookPayload;
    }

    public function processWebhookPayload(
        WebhookPayload $webhookPayload,
        string $provider,
        array $payload,
        array $headers,
        ?WebhookEndpoint $webhookEndpoint = null
    ): void {
        try {
            $providerHandler = $this->providerRegistry->getProvider($provider);
            
            if (!$providerHandler) {
                throw new \Exception("No webhook handler found for provider: {$provider}");
            }

            // Get configuration from endpoint
            $providerConfig = $webhookEndpoint?->getConfiguration() ?? [];
            
            // Verify webhook signature
            if ($this->verifySignatures && !$providerHandler->verifySignature(
                $payload, 
                $headers, 
                $providerConfig['webhook_secret'] ?? null
            )) {
                throw new \Exception('Invalid webhook signature');
            }

            // Parse webhook data
            $parsedData = $providerHandler->parseWebhook($payload);
            
            // Handle inbound messages
            if ($providerHandler->isInboundMessage($payload) && isset($parsedData['message'])) {
                $this->handleInboundMessage($parsedData['message'], $provider, $webhookPayload);
            }
            
            // Handle delivery events
            if ($providerHandler->isDeliveryEvent($payload)) {
                if (isset($parsedData['events'])) {
                    // Handle batch events
                    foreach ($parsedData['events'] as $eventData) {
                        $this->processEvent($eventData, $provider, $webhookPayload);
                    }
                } else {
                    // Handle single event
                    $this->processEvent($parsedData, $provider, $webhookPayload);
                }
            }

            $webhookPayload->setProcessed(true);
            $webhookPayload->setEventType($parsedData['event_type'] ?? 'batch');
            
            // Update endpoint success stats
            if ($webhookEndpoint) {
                $webhookEndpoint->incrementSuccessfulRequests();
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to process webhook', [
                'provider' => $provider,
                'error' => $e->getMessage(),
                'endpoint_id' => $webhookEndpoint?->getId(),
            ]);
            
            $webhookPayload->setProcessed(false);
            $webhookPayload->setProcessingError($e->getMessage());
            
            // Update endpoint failure stats
            if ($webhookEndpoint) {
                $webhookEndpoint->incrementFailedRequests();
            }
        }

        $this->entityManager->flush();
    }

    private function handleInboundMessage(array $messageData, string $provider, WebhookPayload $webhookPayload): void
    {
        try {
            // Create a new inbound message
            $message = $this->notificationTracker->createInboundMessage(
                $messageData['type'] ?? 'email',
                $messageData['from'],
                $messageData['to'],
                $messageData['subject'] ?? null,
                $messageData['content'] ?? [],
                $messageData['metadata'] ?? []
            );

            $message->setTransportName($provider);
            $message->setDirection(Message::DIRECTION_INBOUND);
            
            if (isset($messageData['external_id'])) {
                $message->setMetadata(array_merge(
                    $message->getMetadata(),
                    ['external_id' => $messageData['external_id']]
                ));
            }

            $this->entityManager->persist($message);
            
            $this->logger->info('Created inbound message from webhook', [
                'provider' => $provider,
                'message_id' => (string) $message->getId(),
                'from' => $messageData['from'],
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to create inbound message', [
                'provider' => $provider,
                'error' => $e->getMessage(),
                'message_data' => $messageData,
            ]);
        }
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
            $webhookPayload,
            $eventData['occurred_at'] ?? null
        );
    }
}