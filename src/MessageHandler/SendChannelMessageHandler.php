<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\MessageHandler;

use Doctrine\ORM\EntityManagerInterface;
use Nkamuo\NotificationTrackerBundle\Entity\Message;
use Nkamuo\NotificationTrackerBundle\Entity\EmailMessage;
use Nkamuo\NotificationTrackerBundle\Entity\SmsMessage;
use Nkamuo\NotificationTrackerBundle\Entity\SlackMessage;
use Nkamuo\NotificationTrackerBundle\Message\SendChannelMessage;
use Nkamuo\NotificationTrackerBundle\Service\MessageTracker;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SendChannelMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly MessageTracker $messageTracker,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(SendChannelMessage $message): void
    {
        $messageEntity = $this->entityManager->getRepository(Message::class)
            ->find($message->getMessageId());

        if (!$messageEntity) {
            $this->logger->error('Message entity not found', [
                'message_id' => (string) $message->getMessageId(),
            ]);
            return;
        }

        // Check if message is ready to send (in case of scheduling)
        if (!$messageEntity->isReadyToSend()) {
            $this->logger->info('Message not ready to send yet', [
                'message_id' => (string) $messageEntity->getId(),
                'effective_scheduled_at' => $messageEntity->getEffectiveScheduledAt()?->format('Y-m-d H:i:s'),
            ]);
            return;
        }

        try {
            // Update status to sending
            $messageEntity->setStatus(Message::STATUS_SENDING);
            $this->entityManager->flush();

            // Send based on channel
            match ($message->getChannel()) {
                'email' => $this->sendEmailMessage($messageEntity),
                'sms' => $this->sendSmsMessage($messageEntity),
                'slack' => $this->sendSlackMessage($messageEntity),
                default => throw new \InvalidArgumentException("Unsupported channel: {$message->getChannel()}")
            };

            // Update status to sent
            $messageEntity->setStatus(Message::STATUS_SENT);
            $messageEntity->setSentAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            // Track the send event
            $this->messageTracker->addEvent($messageEntity, 'sent', ['message' => 'Message sent successfully']);

            $this->logger->info('Channel message sent successfully', [
                'message_id' => (string) $messageEntity->getId(),
                'channel' => $message->getChannel(),
            ]);

        } catch (\Exception $e) {
            // Update status to failed
            $messageEntity->setStatus(Message::STATUS_FAILED);
            $messageEntity->setFailureReason($e->getMessage());
            $this->entityManager->flush();

            // Track the failure event
            $this->messageTracker->addEvent($messageEntity, 'failed', ['error' => $e->getMessage()]);

            $this->logger->error('Failed to send channel message', [
                'message_id' => (string) $messageEntity->getId(),
                'channel' => $message->getChannel(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function sendEmailMessage(Message $messageEntity): void
    {
        if (!$messageEntity instanceof EmailMessage) {
            throw new \InvalidArgumentException('Expected EmailMessage entity');
        }

        $content = $messageEntity->getContent();
        $recipients = $messageEntity->getRecipients();
        $notification = $messageEntity->getNotification();

        if ($recipients->isEmpty()) {
            throw new \RuntimeException('No recipients found for email message');
        }

        $primaryRecipient = $recipients->first();

        $email = (new Email())
            ->to($primaryRecipient->getAddress())
            ->subject($notification?->getSubject() ?? 'Notification')
            ->text($content?->getBodyText() ?? '');

        if ($content?->getBodyHtml()) {
            $email->html($content->getBodyHtml());
        }

        if ($primaryRecipient->getName()) {
            $email->to($primaryRecipient->getAddress(), $primaryRecipient->getName());
        }

        // Add tracking header
        $email->getHeaders()->addTextHeader('X-Notification-Tracker-ID', (string) $messageEntity->getId());

        $this->mailer->send($email);
    }

    private function sendSmsMessage(Message $messageEntity): void
    {
        if (!$messageEntity instanceof SmsMessage) {
            throw new \InvalidArgumentException('Expected SmsMessage entity');
        }

        $content = $messageEntity->getContent();
        $recipients = $messageEntity->getRecipients();

        if ($recipients->isEmpty()) {
            throw new \RuntimeException('No recipients found for SMS message');
        }

        $primaryRecipient = $recipients->first();

        // TODO: Integrate with SMS provider (Twilio, AWS SNS, etc.)
        // For now, just log the SMS sending
        $this->logger->info('SMS message sent (mock)', [
            'to' => $primaryRecipient->getAddress(),
            'content' => $content?->getBodyText(),
            'message_id' => (string) $messageEntity->getId(),
        ]);
    }

    private function sendSlackMessage(Message $messageEntity): void
    {
        if (!$messageEntity instanceof SlackMessage) {
            throw new \InvalidArgumentException('Expected SlackMessage entity');
        }

        $content = $messageEntity->getContent();
        $recipients = $messageEntity->getRecipients();

        if ($recipients->isEmpty()) {
            throw new \RuntimeException('No recipients found for Slack message');
        }

        $primaryRecipient = $recipients->first();
        $structuredData = $content?->getStructuredData();

        // TODO: Integrate with Slack API
        // For now, just log the Slack message sending
        $this->logger->info('Slack message sent (mock)', [
            'channel' => $primaryRecipient->getAddress(),
            'content' => $content?->getBodyText(),
            'blocks' => $structuredData['blocks'] ?? null,
            'attachments' => $structuredData['attachments'] ?? null,
            'message_id' => (string) $messageEntity->getId(),
        ]);
    }
}
