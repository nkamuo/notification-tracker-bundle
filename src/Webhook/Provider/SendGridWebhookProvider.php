<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Webhook\Provider;

use Nkamuo\NotificationTrackerBundle\Entity\MessageEvent;

class SendGridWebhookProvider implements WebhookProviderInterface
{
    public function __construct(
        private readonly string $defaultSecret = ''
    ) {
    }

    public function supports(string $provider): bool
    {
        return $provider === 'sendgrid';
    }

    public function getProviderName(): string
    {
        return 'sendgrid';
    }

    public function verifySignature(array $payload, array $headers, ?string $secret = null): bool
    {
        $webhookSecret = $secret ?? $this->defaultSecret;
        
        if (empty($webhookSecret) || !isset($headers['X-Twilio-Email-Event-Webhook-Signature'])) {
            return false;
        }

        $signature = $headers['X-Twilio-Email-Event-Webhook-Signature'];
        $timestamp = $headers['X-Twilio-Email-Event-Webhook-Timestamp'] ?? '';
        
        $signedContent = $timestamp . json_encode($payload);
        $expectedSignature = base64_encode(
            hash_hmac('sha256', $signedContent, $webhookSecret, true)
        );

        return hash_equals($expectedSignature, $signature);
    }

    public function parseWebhook(array $payload): array
    {
        $eventMap = [
            'processed' => MessageEvent::TYPE_QUEUED,
            'dropped' => MessageEvent::TYPE_FAILED,
            'delivered' => MessageEvent::TYPE_DELIVERED,
            'deferred' => MessageEvent::TYPE_FAILED,
            'bounce' => MessageEvent::TYPE_BOUNCED,
            'open' => MessageEvent::TYPE_OPENED,
            'click' => MessageEvent::TYPE_CLICKED,
            'spamreport' => MessageEvent::TYPE_COMPLAINED,
            'unsubscribe' => MessageEvent::TYPE_UNSUBSCRIBED,
        ];

        $events = [];
        foreach ($payload as $event) {
            if (!is_array($event)) {
                continue;
            }

            $eventType = $eventMap[$event['event'] ?? ''] ?? null;
            
            if (!$eventType) {
                continue;
            }

            $occurredAt = null;
            if (isset($event['timestamp'])) {
                $occurredAt = \DateTimeImmutable::createFromFormat('U', (string)$event['timestamp']);
            }

            $events[] = [
                'event_type' => $eventType,
                'message_id' => $event['sg_message_id'] ?? $event['smtp-id'] ?? null,
                'recipient_email' => $event['email'] ?? null,
                'occurred_at' => $occurredAt,
                'event_data' => [
                    'timestamp' => $event['timestamp'] ?? null,
                    'ip' => $event['ip'] ?? null,
                    'user_agent' => $event['useragent'] ?? null,
                    'url' => $event['url'] ?? null,
                    'reason' => $event['reason'] ?? null,
                    'category' => $event['category'] ?? null,
                ],
            ];
        }

        return ['events' => $events];
    }

    public function isInboundMessage(array $payload): bool
    {
        // SendGrid event webhooks are for delivery tracking, not inbound messages
        return false;
    }

    public function isDeliveryEvent(array $payload): bool
    {
        return true;
    }

    public function getConfigurationFields(): array
    {
        return [
            'webhook_secret' => [
                'type' => 'password',
                'label' => 'Webhook Secret',
                'description' => 'The secret key used to verify webhook signatures from SendGrid',
                'required' => true,
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