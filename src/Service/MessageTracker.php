<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Nkamuo\NotificationTrackerBundle\Entity\EmailMessage;
use Nkamuo\NotificationTrackerBundle\Entity\Message;
use Nkamuo\NotificationTrackerBundle\Entity\MessageAttachment;
use Nkamuo\NotificationTrackerBundle\Entity\MessageContent;
use Nkamuo\NotificationTrackerBundle\Entity\MessageEvent;
use Nkamuo\NotificationTrackerBundle\Entity\MessageRecipient;
use Nkamuo\NotificationTrackerBundle\Entity\Notification;
use Nkamuo\NotificationTrackerBundle\Entity\SmsMessage;
use Nkamuo\NotificationTrackerBundle\Entity\SlackMessage;
use Nkamuo\NotificationTrackerBundle\Entity\TelegramMessage;
use Nkamuo\NotificationTrackerBundle\Entity\WebhookPayload;
use Nkamuo\NotificationTrackerBundle\Event\MessageTrackedEvent;
use Nkamuo\NotificationTrackerBundle\Repository\MessageRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage as NotifierSmsMessage;
use Symfony\Component\Uid\Ulid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class MessageTracker
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
        private readonly MessageRepository $messageRepository,
        private readonly AttachmentManager $attachmentManager,
        private readonly bool $storeContent = true
    ) {
    }

    public function trackEmail(
        Email $email,
        ?string $transportName = null,
        ?Notification $notification = null,
        array $metadata = []
    ): EmailMessage {
        // Auto-create notification if none provided (for direct mailer usage)
        if ($notification === null) {
            $notification = $this->createAutoNotification('email', [
                'subject' => $email->getSubject() ?? '(no subject)',
                'channel' => 'email',
                'transport' => $transportName,
                'source' => 'direct_mailer'
            ]);
        }

        $message = new EmailMessage();
        $message->setSubject($email->getSubject() ?? '(no subject)');
        
        $from = $email->getFrom()[0] ?? new Address('noreply@example.com');
        $message->setFromEmail($from->getAddress());
        $message->setFromName($from->getName());
        
        if ($email->getReplyTo()) {
            $message->setReplyTo($email->getReplyTo()[0]->getAddress());
        }

        $message->setTransportName($transportName);
        $message->setNotification($notification);
        $message->setMetadata($metadata);
        
        // Extract stamp ID from metadata if available
        if (isset($metadata['stamp_id'])) {
            $message->setMessengerStampId($metadata['stamp_id']);
        }
        
        // Extract content fingerprint from metadata if available
        if (isset($metadata['content_fingerprint'])) {
            $message->setContentFingerprint($metadata['content_fingerprint']);
        }
        
        // Set headers
        $headers = [];
        foreach ($email->getHeaders()->all() as $header) {
            $headers[$header->getName()] = $header->getBodyAsString();
        }
        $message->setHeaders($headers);
        
        // Add recipients
        $this->addEmailRecipients($message, $email);
        
        // Add content
        if ($this->storeContent) {
            $content = new MessageContent();
            $content->setContentType($email->getHtmlBody() ? 'text/html' : 'text/plain');
            $content->setBodyText($email->getTextBody());
            $content->setBodyHtml($email->getHtmlBody());
            $message->setContent($content);
        }
        
        // Handle attachments
        $this->handleEmailAttachments($message, $email);
        
        // Persist the message first before adding events
        $this->entityManager->persist($message);
        $this->entityManager->flush();
        
        // Add initial event after message is persisted
        $this->addEvent($message, MessageEvent::TYPE_QUEUED, [
            'transport' => $transportName,
        ]);
        
        // Dispatch event
        $this->eventDispatcher->dispatch(new MessageTrackedEvent($message));

        $this->logger->info('Email tracked', [
            'message_id' => (string) $message->getId(),
            'subject' => $message->getSubject(),
            'recipients_count' => count($message->getRecipients()),
        ]);

        return $message;
    }

    public function trackSms(
        NotifierSmsMessage $sms,
        ?string $transportName = null,
        ?Notification $notification = null,
        array $metadata = []
    ): SmsMessage {
        // Create auto-notification if none provided for unified tracking
        if ($notification === null) {
            $notification = $this->createAutoNotification('sms', [
                'subject' => $sms->getSubject(),
                'transport' => $transportName,
                'phone' => $sms->getPhone(),
                'source' => 'direct_sms_tracking'
            ]);
        }

        $message = new SmsMessage();
        $message->setFromNumber($sms->getFrom());
        $message->setTransportName($transportName);
        $message->setNotification($notification);
        $message->setMetadata($metadata);
        
        // Add recipient
        $recipient = new MessageRecipient();
        $recipient->setType(MessageRecipient::TYPE_TO);
        $recipient->setAddress($sms->getPhone());
        $message->addRecipient($recipient);
        
        // Add content
        if ($this->storeContent) {
            $content = new MessageContent();
            $content->setContentType('text/plain');
            $content->setBodyText($sms->getSubject());
            $message->setContent($content);
        }
        
        // Calculate segments
        $textLength = strlen($sms->getSubject());
        $segments = ceil($textLength / 160);
        $message->setSegmentsCount((int)$segments);

        // Persist the message first before adding events
        $this->entityManager->persist($message);
        $this->entityManager->flush();

        // Add initial event after message is persisted
        $this->addEvent($message, MessageEvent::TYPE_QUEUED, [
            'transport' => $transportName,
        ]);
        
        $this->eventDispatcher->dispatch(new MessageTrackedEvent($message));

        return $message;
    }

    public function trackChat(
        ChatMessage $chat,
        string $channelType,
        ?string $transportName = null,
        ?Notification $notification = null,
        array $metadata = []
    ): Message {
        // Create auto-notification if none provided for unified tracking
        if ($notification === null) {
            $notification = $this->createAutoNotification($channelType, [
                'subject' => $chat->getSubject(),
                'transport' => $transportName,
                'channel_type' => $channelType,
                'source' => 'direct_chat_tracking'
            ]);
        }

        $message = match ($channelType) {
            'slack' => $this->createSlackMessage($chat),
            'telegram' => $this->createTelegramMessage($chat),
            default => throw new \InvalidArgumentException("Unsupported chat channel: {$channelType}")
        };
        
        $message->setTransportName($transportName);
        $message->setNotification($notification);
        $message->setMetadata($metadata);
        
        // Add content
        if ($this->storeContent) {
            $content = new MessageContent();
            $content->setContentType('text/plain');
            $content->setBodyText($chat->getSubject());
            $message->setContent($content);
        }
        
        // Persist the message first before adding events
        $this->entityManager->persist($message);
        $this->entityManager->flush();
        
        // Add initial event after message is persisted
        $this->addEvent($message, MessageEvent::TYPE_QUEUED, [
            'transport' => $transportName,
            'channel' => $channelType,
        ]);
        
        $this->eventDispatcher->dispatch(new MessageTrackedEvent($message));

        return $message;
    }

    public function addEvent(
        Message $message,
        string $eventType,
        array $eventData = [],
        ?MessageRecipient $recipient = null,
        ?WebhookPayload $webhookPayload = null
    ): MessageEvent {
        $event = new MessageEvent();
        $event->setMessage($message);
        $event->setEventType($eventType);
        $event->setEventData($eventData);
        $event->setRecipient($recipient);
        $event->setWebhookPayload($webhookPayload);

        if (isset($eventData['ip_address'])) {
            $event->setIpAddress($eventData['ip_address']);
        }

        if (isset($eventData['user_agent'])) {
            $event->setUserAgent($eventData['user_agent']);
        }

        $message->addEvent($event);

        // Update message status based on event
        $this->updateMessageStatus($message, $eventType);

        // Update recipient status if applicable
        if ($recipient) {
            $this->updateRecipientStatus($recipient, $eventType);
        }

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        return $event;
    }

    public function findById(string $id): ?Message
    {
        try {
            $ulid = Ulid::fromString($id);
            return $this->messageRepository->find($ulid);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function findByProviderMessageId(string $providerMessageId, string $provider): ?Message
    {
        return $this->messageRepository->findOneBy([
            'metadata' => ['provider_message_id' => $providerMessageId],
            'transportName' => $provider,
        ]);
    }

    private function addEmailRecipients(EmailMessage $message, Email $email): void
    {
        foreach ($email->getTo() as $address) {
            $recipient = new MessageRecipient();
            $recipient->setType(MessageRecipient::TYPE_TO);
            $recipient->setAddress($address->getAddress());
            $recipient->setName($address->getName());
            $message->addRecipient($recipient);
        }
        
        foreach ($email->getCc() as $address) {
            $recipient = new MessageRecipient();
            $recipient->setType(MessageRecipient::TYPE_CC);
            $recipient->setAddress($address->getAddress());
            $recipient->setName($address->getName());
            $message->addRecipient($recipient);
        }
        
        foreach ($email->getBcc() as $address) {
            $recipient = new MessageRecipient();
            $recipient->setType(MessageRecipient::TYPE_BCC);
            $recipient->setAddress($address->getAddress());
            $recipient->setName($address->getName());
            $message->addRecipient($recipient);
        }
    }

    private function handleEmailAttachments(EmailMessage $message, Email $email): void
    {
        foreach ($email->getAttachments() as $part) {
            if (!$part instanceof DataPart) {
                continue;
            }
            
            $attachment = new MessageAttachment();
            $attachment->setFilename($part->getFilename() ?? 'attachment');
            $attachment->setContentType($part->getContentType() ?? 'application/octet-stream');
            $attachment->setSize(strlen($part->getBody()));
            
            if ($part->hasContentId()) {
                $attachment->setContentId($part->getContentId());
                $attachment->setInline(true);
            }
            
            // Store attachment file
            $path = $this->attachmentManager->store($part->getBody(), $attachment->getFilename());
            $attachment->setPath($path);
            
            $message->addAttachment($attachment);
        }
    }

    private function createSlackMessage(ChatMessage $chat): SlackMessage
    {
        $message = new SlackMessage();
        $message->setChannel($chat->getRecipientId() ?? 'general');
        
        if ($chat->getOptions()) {
            $options = $chat->getOptions()->toArray();
            if (isset($options['blocks'])) {
                $message->setBlocks($options['blocks']);
            }
            if (isset($options['attachments'])) {
                // $message->setAttachments($options['attachments']);
                foreach ($options['attachments'] as $attachmentData) {
                    // Process each attachment data as needed
                    // For now, just log or ignore
                    $this->logger->info('Slack attachment data received', ['attachment' => $attachmentData]);
                    $message->addAttachment($attachmentData);
                }
            }
        }
        
        return $message;
    }

    private function createTelegramMessage(ChatMessage $chat): TelegramMessage
    {
        $message = new TelegramMessage();
        $message->setChatId($chat->getRecipientId() ?? '');
        
        if ($chat->getOptions()) {
            $options = $chat->getOptions()->toArray();
            if (isset($options['parse_mode'])) {
                $message->setParseMode($options['parse_mode']);
            }
            if (isset($options['reply_markup'])) {
                $message->setReplyMarkup($options['reply_markup']);
            }
        }
        
        return $message;
    }

    private function updateMessageStatus(Message $message, string $eventType): void
    {
        $statusMap = [
            MessageEvent::TYPE_QUEUED => Message::STATUS_QUEUED,
            MessageEvent::TYPE_SENT => Message::STATUS_SENT,
            MessageEvent::TYPE_DELIVERED => Message::STATUS_DELIVERED,
            MessageEvent::TYPE_BOUNCED => Message::STATUS_BOUNCED,
            MessageEvent::TYPE_FAILED => Message::STATUS_FAILED,
        ];

        if (isset($statusMap[$eventType])) {
            $message->setStatus($statusMap[$eventType]);
            
            if ($eventType === MessageEvent::TYPE_SENT) {
                $message->setSentAt(new \DateTimeImmutable());
            }
        }

        $message->setUpdatedAt(new \DateTimeImmutable());
    }

    private function updateRecipientStatus(MessageRecipient $recipient, string $eventType): void
    {
        $statusMap = [
            MessageEvent::TYPE_SENT => MessageRecipient::STATUS_SENT,
            MessageEvent::TYPE_DELIVERED => MessageRecipient::STATUS_DELIVERED,
            MessageEvent::TYPE_OPENED => MessageRecipient::STATUS_OPENED,
            MessageEvent::TYPE_CLICKED => MessageRecipient::STATUS_CLICKED,
            MessageEvent::TYPE_BOUNCED => MessageRecipient::STATUS_BOUNCED,
            MessageEvent::TYPE_COMPLAINED => MessageRecipient::STATUS_COMPLAINED,
            MessageEvent::TYPE_UNSUBSCRIBED => MessageRecipient::STATUS_UNSUBSCRIBED,
        ];

        if (isset($statusMap[$eventType])) {
            $recipient->setStatus($statusMap[$eventType]);
            
            $timestampMap = [
                MessageEvent::TYPE_DELIVERED => 'setDeliveredAt',
                MessageEvent::TYPE_OPENED => 'setOpenedAt',
                MessageEvent::TYPE_CLICKED => 'setClickedAt',
                MessageEvent::TYPE_BOUNCED => 'setBouncedAt',
            ];

            if (isset($timestampMap[$eventType])) {
                $method = $timestampMap[$eventType];
                $recipient->$method(new \DateTimeImmutable());
            }
            
            // Increment counters
            if ($eventType === MessageEvent::TYPE_OPENED) {
                $recipient->incrementOpenCount();
            } elseif ($eventType === MessageEvent::TYPE_CLICKED) {
                $recipient->incrementClickCount();
            }
        }
    }

    /**
     * Create an automatic notification for direct channel usage
     * This ensures all messages have a parent notification for unified tracking
     */
    private function createAutoNotification(string $channel, array $context = []): Notification
    {
        $notification = new Notification();
        
        // Generate a descriptive subject based on the channel and context
        $subject = $this->generateAutoNotificationSubject($channel, $context);
        $notification->setSubject($subject);
        
        // Set type as auto-generated
        $notification->setType('auto_generated');
        
        // Set channels
        $notification->setChannels([$channel]);
        
        // Set context with metadata to indicate this was auto-created
        $notification->setContext([
            'auto_generated' => true,
            'source' => $context['source'] ?? 'unknown',
            'original_channel' => $channel,
            'created_by' => 'MessageTracker',
            'context' => $context
        ]);
        
        // Persist the notification first
        $this->entityManager->persist($notification);
        $this->entityManager->flush();
        
        $this->logger->info('Auto-created notification for direct channel usage', [
            'notification_id' => (string) $notification->getId(),
            'channel' => $channel,
            'subject' => $subject,
            'source' => $context['source'] ?? 'unknown'
        ]);
        
        return $notification;
    }

    /**
     * Generate a descriptive subject for auto-created notifications
     */
    private function generateAutoNotificationSubject(string $channel, array $context): string
    {
        $subject = $context['subject'] ?? null;
        $source = $context['source'] ?? 'direct';
        
        if ($subject) {
            return sprintf('[%s] %s', ucfirst($channel), $subject);
        }
        
        return sprintf('[%s] Message sent via %s', ucfirst($channel), $source);
    }
}