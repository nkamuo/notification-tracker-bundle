<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Webhook\Provider;

use Nkamuo\NotificationTrackerBundle\Entity\MessageEvent;

class MailgunWebhookProvider implements WebhookProviderInterface
{
    public function __construct(
        private readonly string $secret = ''
    ) {
    }

    public function supports(string $provider): bool
    {
        return $provider === 'mailgun';
    }

    public function getProviderName(): string
    {
        return 'mailgun';
    }

    public function verifySignature(array $payload, array $headers, ?string $secret = null): bool
    {
        $webhookSecret = $secret ?? $this->secret;
        
        if (empty($webhookSecret) || !isset($headers['X-Mailgun-Signature'])) {
            return false;
        }

        $signature = $headers['X-Mailgun-Signature'];
        $timestamp = $headers['X-Mailgun-Timestamp'] ?? '';
        $token = $headers['X-Mailgun-Token'] ?? '';
        
        $expectedSignature = hash_hmac('sha256', $timestamp . $token, $webhookSecret);

        return hash_equals($expectedSignature, $signature);
    }

    public function parseWebhook(array $payload): array
    {
        // Check if this is a delivery event or inbound message
        if (isset($payload['event'])) {
            return $this->parseDeliveryEvent($payload);
        } elseif (isset($payload['recipient']) && isset($payload['sender'])) {
            return $this->parseInboundMessage($payload);
        }

        return [];
    }

    public function isInboundMessage(array $payload): bool
    {
        return isset($payload['recipient']) && isset($payload['sender']) && !isset($payload['event']);
    }

    public function isDeliveryEvent(array $payload): bool
    {
        return isset($payload['event']);
    }

    private function parseDeliveryEvent(array $payload): array
    {
        $eventMap = [
            'delivered' => MessageEvent::TYPE_DELIVERED,
            'opened' => MessageEvent::TYPE_OPENED,
            'clicked' => MessageEvent::TYPE_CLICKED,
            'unsubscribed' => MessageEvent::TYPE_UNSUBSCRIBED,
            'complained' => MessageEvent::TYPE_COMPLAINED,
            'bounced' => MessageEvent::TYPE_BOUNCED,
            'failed' => MessageEvent::TYPE_FAILED,
        ];

        $eventType = $eventMap[$payload['event']] ?? null;
        
        if (!$eventType) {
            return [];
        }

        $occurredAt = null;
        if (isset($payload['timestamp'])) {
            $occurredAt = \DateTimeImmutable::createFromFormat('U', (string)$payload['timestamp']);
        }

        return [
            'event_type' => $eventType,
            'message_id' => $payload['Message-Id'] ?? $payload['message-id'] ?? null,
            'recipient_email' => $payload['recipient'] ?? null,
            'occurred_at' => $occurredAt,
            'event_data' => [
                'timestamp' => $payload['timestamp'] ?? null,
                'ip' => $payload['ip'] ?? null,
                'country' => $payload['country'] ?? null,
                'region' => $payload['region'] ?? null,
                'city' => $payload['city'] ?? null,
                'user_agent' => $payload['user-agent'] ?? null,
                'url' => $payload['url'] ?? null,
                'reason' => $payload['reason'] ?? null,
                'code' => $payload['code'] ?? null,
                'description' => $payload['description'] ?? null,
            ],
        ];
    }

    private function parseInboundMessage(array $payload): array
    {
        return [
            'message' => [
                'direction' => 'inbound',
                'type' => 'email',
                'external_id' => $payload['Message-Id'] ?? null,
                'from' => $payload['sender'],
                'to' => isset($payload['recipient']) ? [$payload['recipient']] : [],
                'subject' => $payload['subject'] ?? null,
                'content' => [
                    'text' => $payload['body-plain'] ?? null,
                    'html' => $payload['body-html'] ?? null,
                ],
                'metadata' => [
                    'mailgun_timestamp' => $payload['timestamp'] ?? null,
                    'mailgun_token' => $payload['token'] ?? null,
                    'stripped_text' => $payload['stripped-text'] ?? null,
                    'stripped_html' => $payload['stripped-html'] ?? null,
                    'stripped_signature' => $payload['stripped-signature'] ?? null,
                    'content_id_map' => $payload['content-id-map'] ?? null,
                ],
            ],
        ];
    }

    public function getConfigurationFields(): array
    {
        return [
            'webhook_secret' => [
                'type' => 'password',
                'label' => 'Webhook Secret',
                'description' => 'The secret key used to verify webhook signatures from Mailgun',
                'required' => true,
            ],
            'handle_inbound' => [
                'type' => 'boolean',
                'label' => 'Handle Inbound Messages',
                'description' => 'Process inbound messages received via Mailgun webhooks',
                'default' => true,
            ],
            'handle_events' => [
                'type' => 'boolean',
                'label' => 'Handle Delivery Events',
                'description' => 'Process delivery events (opens, clicks, bounces, etc.)',
                'default' => true,
            ],
            'enabled' => [
                'type' => 'boolean',
                'label' => 'Enabled',
                'description' => 'Enable webhook processing for this provider',
                'default' => true,
            ],
        ];
    }

    public function validateConfiguration(array $config): array
    {
        $errors = [];

        if (empty($config['webhook_secret'])) {
            $errors['webhook_secret'] = 'Webhook secret is required';
        }

        return $errors;
    }
}
