<?php

declare(strict_types=1);

namespace App\Service;

use Nkamuo\NotificationTrackerBundle\Messenger\Stamp\NotificationProviderStamp;
use Nkamuo\NotificationTrackerBundle\Messenger\Stamp\NotificationCampaignStamp;
use Nkamuo\NotificationTrackerBundle\Messenger\Stamp\NotificationTemplateStamp;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * Example service showing how to use the custom notification tracking transport
 */
class NotificationDispatcherExample
{
    public function __construct(
        private MessageBusInterface $messageBus
    ) {}

    /**
     * Example: Send email notification with campaign tracking
     */
    public function sendCampaignEmail(string $email, array $campaignData): void
    {
        $message = new EmailNotificationMessage($email, $campaignData);

        // Dispatch with notification tracking stamps
        $this->messageBus->dispatch($message, [
            // Identify this as an email notification with high priority
            new NotificationProviderStamp('email', priority: 10),
            
            // Track campaign association
            new NotificationCampaignStamp(
                campaignId: $campaignData['campaign_id'],
                campaignName: $campaignData['campaign_name'] ?? null
            ),
            
            // Track template usage
            new NotificationTemplateStamp(
                templateId: $campaignData['template_id'],
                templateName: $campaignData['template_name'] ?? null
            ),
        ]);
    }

    /**
     * Example: Send SMS with provider-aware routing
     */
    public function sendSmsNotification(string $phoneNumber, string $message, string $provider = 'twilio'): void
    {
        $smsMessage = new SmsNotificationMessage($phoneNumber, $message);

        $this->messageBus->dispatch($smsMessage, [
            // Route to provider-specific queue (if provider_aware_routing=true)
            new NotificationProviderStamp($provider, priority: 5),
        ]);
    }

    /**
     * Example: Send delayed push notification
     */
    public function sendDelayedPushNotification(string $deviceToken, array $payload, int $delayMinutes = 0): void
    {
        $pushMessage = new PushNotificationMessage($deviceToken, $payload);

        $stamps = [
            new NotificationProviderStamp('fcm', priority: 8),
        ];

        // Add delay if specified
        if ($delayMinutes > 0) {
            $stamps[] = new DelayStamp($delayMinutes * 60 * 1000); // Convert to milliseconds
        }

        $this->messageBus->dispatch($pushMessage, $stamps);
    }

    /**
     * Example: Send urgent notification to multiple transports
     */
    public function sendUrgentNotification(array $recipients, string $message): void
    {
        $urgentMessage = new UrgentNotificationMessage($recipients, $message);

        // This will be routed to multiple transports as configured in messenger.yaml
        $this->messageBus->dispatch($urgentMessage, [
            new NotificationProviderStamp('urgent', priority: 100),
            new NotificationCampaignStamp('urgent-alerts'),
        ]);
    }

    /**
     * Example: Send newsletter with analytics tracking
     */
    public function sendNewsletter(string $email, array $newsletterData): void
    {
        $message = new NewsletterMessage($email, $newsletterData);

        $this->messageBus->dispatch($message, [
            new NotificationProviderStamp('email', priority: 1), // Low priority for newsletters
            new NotificationCampaignStamp(
                campaignId: 'newsletter-' . date('Y-m'),
                campaignName: 'Monthly Newsletter ' . date('F Y')
            ),
            new NotificationTemplateStamp(
                templateId: 'newsletter-template-v2',
                templateName: 'Newsletter Template v2'
            ),
        ]);
    }
}

// Example Message Classes (you would create these in your application)

class EmailNotificationMessage
{
    public function __construct(
        public readonly string $email,
        public readonly array $data
    ) {}
}

class SmsNotificationMessage
{
    public function __construct(
        public readonly string $phoneNumber,
        public readonly string $message
    ) {}
}

class PushNotificationMessage
{
    public function __construct(
        public readonly string $deviceToken,
        public readonly array $payload
    ) {}
}

class UrgentNotificationMessage
{
    public function __construct(
        public readonly array $recipients,
        public readonly string $message
    ) {}
}

class NewsletterMessage
{
    public function __construct(
        public readonly string $email,
        public readonly array $data
    ) {}
}
