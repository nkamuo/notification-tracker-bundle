<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Webhook\Provider;

use Nkamuo\NotificationTrackerBundle\Entity\Message;

interface WebhookProviderInterface
{
    /**
     * Check if this provider supports the given provider name
     */
    public function supports(string $provider): bool;
    
    /**
     * Get the provider name this handler supports
     */
    public function getProviderName(): string;
    
    /**
     * Verify webhook signature
     */
    public function verifySignature(array $payload, array $headers, ?string $secret = null): bool;
    
    /**
     * Parse webhook payload and return standardized data
     * 
     * @return array{
     *   events?: array<array{
     *     event_type: string,
     *     message_id?: string,
     *     recipient_email?: string,
     *     event_data?: array,
     *     occurred_at?: \DateTimeImmutable
     *   }>,
     *   message?: array{
     *     direction: string,
     *     type: string,
     *     external_id?: string,
     *     from: string,
     *     to: array<string>,
     *     subject?: string,
     *     content?: array{
     *       text?: string,
     *       html?: string
     *     },
     *     metadata?: array
     *   },
     *   event_type?: string,
     *   message_id?: string,
     *   recipient_email?: string,
     *   event_data?: array,
     *   occurred_at?: \DateTimeImmutable
     * }
     */
    public function parseWebhook(array $payload): array;
    
    /**
     * Check if this webhook contains inbound message data
     */
    public function isInboundMessage(array $payload): bool;
    
    /**
     * Check if this webhook contains delivery/tracking events
     */
    public function isDeliveryEvent(array $payload): bool;
    
    /**
     * Get configuration fields that this provider needs
     * 
     * @return array<string, array{
     *   type: string,
     *   label: string,
     *   description?: string,
     *   required?: bool,
     *   default?: mixed,
     *   options?: array
     * }>
     */
    public function getConfigurationFields(): array;
    
    /**
     * Validate provider configuration
     */
    public function validateConfiguration(array $config): array;
}