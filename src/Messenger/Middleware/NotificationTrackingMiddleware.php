<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Messenger\Middleware;

use Nkamuo\NotificationTrackerBundle\Messenger\Stamp\NotificationTrackingStamp;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\DesktopMessage;
use Symfony\Component\Notifier\Message\EmailMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\PushMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Uid\Ulid;

/**
 * Middleware that automatically adds NotificationTrackingStamp to notification messages
 * if they don't already have one. This ensures every notification has a unique identifier
 * that persists across retries and transport failures.
 * 
 * Supports:
 * - Mailer: SendEmailMessage
 * - Notifier: SmsMessage, PushMessage, ChatMessage, EmailMessage, DesktopMessage
 */
class NotificationTrackingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();
        
        $this->logger->debug('NotificationTrackingMiddleware: Processing message', [
            'message_type' => get_class($message),
            'is_trackable' => $this->isTrackableMessage($message)
        ]);
        
        // Only process notification-related messages
        if (!$this->isTrackableMessage($message)) {
            return $stack->next()->handle($envelope, $stack);
        }

        // Add notification tracking stamp if not present
        $stamp = $envelope->last(NotificationTrackingStamp::class);
        if (null === $stamp) {
            $trackingId = (string) new Ulid();
            $stamp = new NotificationTrackingStamp($trackingId);
            $envelope = $envelope->with($stamp);
            
            $this->logger->info('NotificationTrackingMiddleware: Added tracking stamp', [
                'tracking_id' => $trackingId,
                'message_type' => get_class($message)
            ]);
        } else {
            $this->logger->debug('NotificationTrackingMiddleware: Stamp already exists', [
                'tracking_id' => $stamp->getId()
            ]);
        }

        // Add tracking headers/metadata based on message type
        $this->addTrackingMetadata($message, $stamp);

        return $stack->next()->handle($envelope, $stack);
    }

    /**
     * Check if a message should be tracked by this middleware.
     */
    private function isTrackableMessage(object $message): bool
    {
        return $message instanceof SendEmailMessage
            || $message instanceof MessageInterface;
    }

    /**
     * Add tracking metadata to the message based on its type.
     */
    private function addTrackingMetadata(object $message, NotificationTrackingStamp $stamp): void
    {
        match (true) {
            $message instanceof SendEmailMessage => $this->addEmailTrackingHeaders($message, $stamp),
            $message instanceof SmsMessage => $this->addSmsTrackingData($message, $stamp),
            $message instanceof PushMessage => $this->addPushTrackingData($message, $stamp),
            $message instanceof ChatMessage => $this->addChatTrackingData($message, $stamp),
            $message instanceof EmailMessage => $this->addNotifierEmailTrackingData($message, $stamp),
            $message instanceof DesktopMessage => $this->addDesktopTrackingData($message, $stamp),
            default => null, // No specific tracking metadata for other message types
        };
    }

    /**
     * Add tracking headers to email messages (Mailer).
     */
    private function addEmailTrackingHeaders(SendEmailMessage $message, NotificationTrackingStamp $stamp): void
    {
        $email = $message->getMessage();
        if ($email instanceof Email && !$email->getHeaders()->has('X-Stamp-ID')) {
            $email->getHeaders()->addTextHeader('X-Stamp-ID', $stamp->getId());
            $email->getHeaders()->addTextHeader('X-Notification-Tracker', 'enabled');
        }
    }

    /**
     * Add tracking data to SMS messages (Notifier).
     * Note: SMS tracking is handled via the stamp, as SMS doesn't support headers.
     */
    private function addSmsTrackingData(SmsMessage $message, NotificationTrackingStamp $stamp): void
    {
        // SMS messages don't support headers, tracking is handled via the stamp
        // Additional tracking could be added to message options if the transport supports it
    }

    /**
     * Add tracking data to push messages (Notifier).
     * Note: Push tracking is handled via the stamp, may add to message options if supported.
     */
    private function addPushTrackingData(PushMessage $message, NotificationTrackingStamp $stamp): void
    {
        // Push messages tracking is handled via the stamp
        // Some push providers support custom data that could include tracking ID
    }

    /**
     * Add tracking data to chat messages (Notifier).
     * Note: Chat tracking is handled via the stamp.
     */
    private function addChatTrackingData(ChatMessage $message, NotificationTrackingStamp $stamp): void
    {
        // Chat messages tracking is handled via the stamp
        // Some chat providers support metadata that could include tracking ID
    }

    /**
     * Add tracking data to email messages (Notifier).
     * Note: This is different from SendEmailMessage - this is Notifier's EmailMessage.
     */
    private function addNotifierEmailTrackingData(EmailMessage $message, NotificationTrackingStamp $stamp): void
    {
        // Notifier EmailMessage tracking is handled via the stamp
        // Could potentially add to message options if the transport supports it
    }

    /**
     * Add tracking data to desktop messages (Notifier).
     */
    private function addDesktopTrackingData(DesktopMessage $message, NotificationTrackingStamp $stamp): void
    {
        // Desktop messages tracking is handled via the stamp
    }
}
