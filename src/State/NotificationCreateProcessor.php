<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Nkamuo\NotificationTrackerBundle\Entity\Notification;
use Nkamuo\NotificationTrackerBundle\Entity\EmailMessage;
use Nkamuo\NotificationTrackerBundle\Entity\SmsMessage;
use Nkamuo\NotificationTrackerBundle\Entity\PushMessage;
use Nkamuo\NotificationTrackerBundle\Entity\SlackMessage;
use Nkamuo\NotificationTrackerBundle\Entity\TelegramMessage;
use Nkamuo\NotificationTrackerBundle\Entity\MessageRecipient;
use Nkamuo\NotificationTrackerBundle\Entity\MessageContent;
use Symfony\Component\Uid\Ulid;

class NotificationCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): Notification
    {
        if (!$data instanceof Notification) {
            throw new \InvalidArgumentException('Expected Notification entity');
        }

        // Persist the notification first
        $this->entityManager->persist($data);

        // Create messages based on channels and recipients
        $this->createMessagesFromNotification($data);

        $this->entityManager->flush();

        return $data;
    }

    private function createMessagesFromNotification(Notification $notification): void
    {
        $channels = $notification->getChannels();
        $recipients = $notification->getRecipients() ?? [];
        $content = $notification->getContent();
        $channelSettings = $notification->getChannelSettings() ?? [];

        if (empty($channels) || empty($recipients)) {
            return; // No channels or recipients configured
        }

        foreach ($channels as $channel) {
            $this->createMessagesForChannel($notification, $channel, $recipients, $content, $channelSettings[$channel] ?? []);
        }
    }

    private function createMessagesForChannel(
        Notification $notification,
        string $channel,
        array $recipients,
        ?string $content,
        array $channelConfig
    ): void {
        switch (strtolower($channel)) {
            case 'email':
                $this->createEmailMessages($notification, $recipients, $content, $channelConfig);
                break;
            case 'sms':
                $this->createSmsMessages($notification, $recipients, $content, $channelConfig);
                break;
            case 'push':
                $this->createPushMessages($notification, $recipients, $content, $channelConfig);
                break;
            case 'slack':
                $this->createSlackMessages($notification, $recipients, $content, $channelConfig);
                break;
            case 'telegram':
                $this->createTelegramMessages($notification, $recipients, $content, $channelConfig);
                break;
        }
    }

    private function createEmailMessages(
        Notification $notification,
        array $recipients,
        ?string $content,
        array $config
    ): void {
        foreach ($recipients as $recipientData) {
            if (!isset($recipientData['email'])) {
                continue;
            }

            $message = new EmailMessage();
            $message->setNotification($notification);
            $message->setTransportName($config['transport'] ?? 'default');
            $message->setFromEmail($config['from_email'] ?? 'noreply@example.com');
            $message->setFromName($config['from_name'] ?? '');
            $message->setSubject($notification->getSubject() ?? $config['subject'] ?? 'Notification');
            
            // Create recipient
            $recipient = new MessageRecipient();
            $recipient->setMessage($message);
            $recipient->setType('to');
            $recipient->setAddress($recipientData['email']);
            $recipient->setName($recipientData['name'] ?? '');
            
            $message->addRecipient($recipient);

            // Create content
            if ($content) {
                $messageContent = new MessageContent();
                $messageContent->setMessage($message);
                $messageContent->setContentType('text/html');
                $messageContent->setBodyHtml($content);
                
                $message->setContent($messageContent);
            }

            $this->entityManager->persist($message);
        }
    }

    private function createSmsMessages(
        Notification $notification,
        array $recipients,
        ?string $content,
        array $config
    ): void {
        foreach ($recipients as $recipientData) {
            if (!isset($recipientData['phone'])) {
                continue;
            }

            $message = new SmsMessage();
            $message->setNotification($notification);
            $message->setTransportName($config['transport'] ?? 'default');
            $message->setFromNumber($config['from_number'] ?? '');
            
            // Create recipient
            $recipient = new MessageRecipient();
            $recipient->setMessage($message);
            $recipient->setType('to');
            $recipient->setAddress($recipientData['phone']);
            $recipient->setName($recipientData['name'] ?? '');
            
            $message->addRecipient($recipient);

            // Create content
            if ($content) {
                $messageContent = new MessageContent();
                $messageContent->setMessage($message);
                $messageContent->setContentType('text/plain');
                $messageContent->setBodyText($content);
                
                $message->setContent($messageContent);
            }

            $this->entityManager->persist($message);
        }
    }

    private function createPushMessages(
        Notification $notification,
        array $recipients,
        ?string $content,
        array $config
    ): void {
        foreach ($recipients as $recipientData) {
            if (!isset($recipientData['device_token']) && !isset($recipientData['user_id'])) {
                continue;
            }

            $message = new PushMessage();
            $message->setNotification($notification);
            $message->setTransportName($config['transport'] ?? 'default');
            $message->setTitle($notification->getSubject() ?? $config['title'] ?? 'Notification');
            
            if ($content) {
                $message->setBody($content);
            }

            // Create recipient
            $recipient = new MessageRecipient();
            $recipient->setMessage($message);
            $recipient->setType('to');
            $recipient->setName($recipientData['name'] ?? '');
            
            if (isset($recipientData['device_token'])) {
                $recipient->setAddress($recipientData['device_token']);
            } elseif (isset($recipientData['user_id'])) {
                $recipient->setAddress($recipientData['user_id']);
            }
            
            $message->addRecipient($recipient);

            // Create content
            if ($content) {
                $messageContent = new MessageContent();
                $messageContent->setMessage($message);
                $messageContent->setContentType('text/plain');
                $messageContent->setBodyText($content);
                
                $message->setContent($messageContent);
            }

            $this->entityManager->persist($message);
        }
    }

    private function createSlackMessages(
        Notification $notification,
        array $recipients,
        ?string $content,
        array $config
    ): void {
        foreach ($recipients as $recipientData) {
            if (!isset($recipientData['channel']) && !isset($recipientData['user_id'])) {
                continue;
            }

            $message = new SlackMessage();
            $message->setNotification($notification);
            $message->setTransportName($config['transport'] ?? 'default');
            
            if (isset($recipientData['channel'])) {
                $message->setChannel($recipientData['channel']);
            }

            // Create recipient
            $recipient = new MessageRecipient();
            $recipient->setMessage($message);
            $recipient->setType('to');
            $recipient->setName($recipientData['name'] ?? '');
            $recipient->setAddress($recipientData['channel'] ?? $recipientData['user_id'] ?? '');
            
            $message->addRecipient($recipient);

            // Create content
            if ($content) {
                $messageContent = new MessageContent();
                $messageContent->setMessage($message);
                $messageContent->setContentType('text/plain');
                $messageContent->setBodyText($content);
                
                $message->setContent($messageContent);
            }

            $this->entityManager->persist($message);
        }
    }

    private function createTelegramMessages(
        Notification $notification,
        array $recipients,
        ?string $content,
        array $config
    ): void {
        foreach ($recipients as $recipientData) {
            if (!isset($recipientData['chat_id']) && !isset($recipientData['user_id'])) {
                continue;
            }

            $message = new TelegramMessage();
            $message->setNotification($notification);
            $message->setTransportName($config['transport'] ?? 'default');
            
            if (isset($recipientData['chat_id'])) {
                $message->setChatId($recipientData['chat_id']);
            }

            // Create recipient
            $recipient = new MessageRecipient();
            $recipient->setMessage($message);
            $recipient->setType('to');
            $recipient->setName($recipientData['name'] ?? '');
            $recipient->setAddress($recipientData['chat_id'] ?? $recipientData['user_id'] ?? '');
            
            $message->addRecipient($recipient);

            // Create content
            if ($content) {
                $messageContent = new MessageContent();
                $messageContent->setMessage($message);
                $messageContent->setContentType('text/plain');
                $messageContent->setBodyText($content);
                
                $message->setContent($messageContent);
            }

            $this->entityManager->persist($message);
        }
    }
}
