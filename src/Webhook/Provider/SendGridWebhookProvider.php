<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Webhook\Provider;

use Nkamuo\NotificationTrackerBundle\Entity\MessageEvent;

class SendGridWebhookProvider implements WebhookProviderInterface
{
    public function __construct(
        private readonly string $secret
    ) {
    }

    public function supports(string $provider): bool
    {
        return $provider === 'sendgrid';
    }

    public function verifySignature(array $payload, array $headers): bool
    {
        if (!isset($headers['X-Twilio-Email-Event-Webhook-Signature'])) {
            return false;
        }

        $signature = $headers['X-Twilio-Email-Event-Webhook-Signature'];
        $timestamp = $headers['X-Twilio-Email-Event-Webhook-Timestamp'] ?? '';
        
        $signedContent = $timestamp . json_encode($payload);
        $expectedSignature = base64_encode(
            hash_hmac('sha256', $signedContent, $this->secret, true)
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

            $events[] = [
                'event_type' => $eventType,
                'message_id' => $event['sg_message_id'] ?? $event['smtp-id'] ?? null,
                'recipient_email' => $event['email'] ?? null,
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
}