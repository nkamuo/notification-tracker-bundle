<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Nkamuo\NotificationTrackerBundle\Entity\NotificationDraft;
use Nkamuo\NotificationTrackerBundle\Entity\EmailMessage;
use Nkamuo\NotificationTrackerBundle\Entity\SmsMessage;
use Nkamuo\NotificationTrackerBundle\Entity\SlackMessage;
use Nkamuo\NotificationTrackerBundle\Entity\MessageContent;
use Nkamuo\NotificationTrackerBundle\Entity\MessageRecipient;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\EmailRecipient;
use Symfony\Component\Notifier\Recipient\Recipient;

class NotificationSender
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly NotifierInterface $notifier,
        private readonly MessageTracker $messageTracker,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Send a notification draft
     */
    public function sendDraft(NotificationDraft $draft): array
    {
        $messagesCreated = 0;
        $channelsUsed = [];
        $recipientsNotified = 0;

        $channels = $draft->getChannels();
        $recipients = $draft->getRecipients();

        // Group recipients by channel for efficient processing
        $recipientsByChannel = [];
        foreach ($recipients as $recipient) {
            $channel = $recipient['channel'] ?? 'email';
            $recipientsByChannel[$channel][] = $recipient;
        }

        // Send to each channel
        foreach ($channels as $channel) {
            if (!isset($recipientsByChannel[$channel])) {
                continue;
            }

            $channelRecipients = $recipientsByChannel[$channel];
            
            switch ($channel) {
                case 'email':
                    $result = $this->sendEmailMessages($draft, $channelRecipients);
                    break;
                case 'sms':
                    $result = $this->sendSmsMessages($draft, $channelRecipients);
                    break;
                case 'slack':
                    $result = $this->sendSlackMessages($draft, $channelRecipients);
                    break;
                default:
                    $this->logger->warning('Unsupported channel', ['channel' => $channel]);
                    continue 2;
            }

            $messagesCreated += $result['messages'];
            $recipientsNotified += $result['recipients'];
            $channelsUsed[] = $channel;
        }

        return [
            'messages_created' => $messagesCreated,
            'channels_used' => $channelsUsed,
            'recipients_notified' => $recipientsNotified,
        ];
    }

    private function sendEmailMessages(NotificationDraft $draft, array $recipients): array
    {
        $messagesCreated = 0;
        $recipientsNotified = 0;

        foreach ($recipients as $recipientData) {
            try {
                // Create tracked message
                $message = new EmailMessage();
                $message->setDraft($draft);
                $message->setDirection($message::DIRECTION_OUTBOUND);
                $message->setStatus($message::STATUS_QUEUED);

                // Create content
                $content = new MessageContent();
                $content->setMessage($message);
                if ($draft->getTextContent()) {
                    $content->setBodyText($draft->getTextContent());
                }
                if ($draft->getHtmlContent()) {
                    $content->setBodyHtml($draft->getHtmlContent());
                }
                $message->setContent($content);

                // Create recipient
                $recipient = new MessageRecipient();
                $recipient->setMessage($message);
                $recipient->setAddress($recipientData['address']);
                $recipient->setName($recipientData['name'] ?? null);
                $recipient->setType(MessageRecipient::TYPE_TO);
                $recipient->setStatus(MessageRecipient::STATUS_PENDING);
                $message->addRecipient($recipient);

                // Copy labels from draft
                foreach ($draft->getLabels() as $label) {
                    $message->addLabel($label);
                }

                $this->entityManager->persist($message);

                // Create and send Symfony email
                $email = (new Email())
                    ->to($recipientData['address'])
                    ->subject($draft->getSubject());

                if ($draft->getTextContent()) {
                    $email->text($draft->getTextContent());
                }
                if ($draft->getHtmlContent()) {
                    $email->html($draft->getHtmlContent());
                }

                // Set a custom header to link with our tracked message
                $email->getHeaders()->addTextHeader('X-Notification-Tracker-ID', (string) $message->getId());

                $this->mailer->send($email);

                $messagesCreated++;
                $recipientsNotified++;

                $this->logger->info('Email sent successfully', [
                    'draft_id' => (string) $draft->getId(),
                    'message_id' => (string) $message->getId(),
                    'recipient' => $recipientData['address'],
                ]);

            } catch (\Exception $e) {
                $this->logger->error('Failed to send email', [
                    'draft_id' => (string) $draft->getId(),
                    'recipient' => $recipientData['address'],
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        $this->entityManager->flush();

        return [
            'messages' => $messagesCreated,
            'recipients' => $recipientsNotified,
        ];
    }

    private function sendSmsMessages(NotificationDraft $draft, array $recipients): array
    {
        $messagesCreated = 0;
        $recipientsNotified = 0;

        foreach ($recipients as $recipientData) {
            try {
                // Create tracked message
                $message = new SmsMessage();
                $message->setDraft($draft);
                $message->setDirection($message::DIRECTION_OUTBOUND);
                $message->setStatus($message::STATUS_QUEUED);

                // Create content
                $content = new MessageContent();
                $content->setMessage($message);
                $content->setBodyText($draft->getTextContent() ?: $draft->getSubject());
                $message->setContent($content);

                // Create recipient
                $recipient = new MessageRecipient();
                $recipient->setMessage($message);
                $recipient->setAddress($recipientData['address']);
                $recipient->setName($recipientData['name'] ?? null);
                $recipient->setType(MessageRecipient::TYPE_TO);
                $recipient->setStatus(MessageRecipient::STATUS_PENDING);
                $message->addRecipient($recipient);

                // Copy labels from draft
                foreach ($draft->getLabels() as $label) {
                    $message->addLabel($label);
                }

                $this->entityManager->persist($message);

                // Send via Symfony Notifier
                $notification = new Notification($draft->getSubject());
                $notification->content($draft->getTextContent() ?: $draft->getSubject());
                
                $recipient = new Recipient($recipientData['address']);
                $this->notifier->send($notification, $recipient);

                $messagesCreated++;
                $recipientsNotified++;

            } catch (\Exception $e) {
                $this->logger->error('Failed to send SMS', [
                    'draft_id' => (string) $draft->getId(),
                    'recipient' => $recipientData['address'],
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        $this->entityManager->flush();

        return [
            'messages' => $messagesCreated,
            'recipients' => $recipientsNotified,
        ];
    }

    private function sendSlackMessages(NotificationDraft $draft, array $recipients): array
    {
        $messagesCreated = 0;
        $recipientsNotified = 0;

        foreach ($recipients as $recipientData) {
            try {
                // Create tracked message
                $message = new SlackMessage();
                $message->setDraft($draft);
                $message->setDirection($message::DIRECTION_OUTBOUND);
                $message->setStatus($message::STATUS_QUEUED);

                // Create content
                $content = new MessageContent();
                $content->setMessage($message);
                $content->setBodyText($draft->getTextContent() ?: $draft->getSubject());
                $message->setContent($content);

                // Create recipient
                $recipient = new MessageRecipient();
                $recipient->setMessage($message);
                $recipient->setAddress($recipientData['address']); // Slack channel or user ID
                $recipient->setName($recipientData['name'] ?? null);
                $recipient->setType(MessageRecipient::TYPE_TO);
                $recipient->setStatus(MessageRecipient::STATUS_PENDING);
                $message->addRecipient($recipient);

                // Copy labels from draft
                foreach ($draft->getLabels() as $label) {
                    $message->addLabel($label);
                }

                $this->entityManager->persist($message);

                // Send via Symfony Notifier (requires Slack notifier configuration)
                $notification = new Notification($draft->getSubject());
                $notification->content($draft->getTextContent() ?: $draft->getSubject());
                
                // For Slack, we'd need a custom recipient or use chat channel
                // This is a simplified implementation
                $this->notifier->send($notification);

                $messagesCreated++;
                $recipientsNotified++;

            } catch (\Exception $e) {
                $this->logger->error('Failed to send Slack message', [
                    'draft_id' => (string) $draft->getId(),
                    'recipient' => $recipientData['address'],
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        $this->entityManager->flush();

        return [
            'messages' => $messagesCreated,
            'recipients' => $recipientsNotified,
        ];
    }
}
