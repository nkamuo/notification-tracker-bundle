<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Controller\Api;

use Doctrine\ORM\EntityManagerInterface;
use Nkamuo\NotificationTrackerBundle\Entity\EmailMessage;
use Nkamuo\NotificationTrackerBundle\Entity\MessageContent;
use Nkamuo\NotificationTrackerBundle\Entity\MessageRecipient;
use Nkamuo\NotificationTrackerBundle\Enum\NotificationDirection;
use Nkamuo\NotificationTrackerBundle\Enum\MessageStatus;
use Nkamuo\NotificationTrackerBundle\Service\MessageTracker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Psr\Log\LoggerInterface;

#[AsController]
class SendEmailController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly MessageTracker $messageTracker,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $requestData = json_decode($request->getContent(), true);
        
        // Validate required fields
        $requiredFields = ['to', 'subject'];
        foreach ($requiredFields as $field) {
            if (!isset($requestData[$field])) {
                return new JsonResponse([
                    'error' => "Field '{$field}' is required"
                ], 400);
            }
        }

        try {
            // Create tracked message
            $message = new EmailMessage();
            $message->setDirection(NotificationDirection::OUTBOUND);
            $message->setStatus(MessageStatus::QUEUED);

            // Set labels if provided
            if (isset($requestData['labels']) && is_array($requestData['labels'])) {
                $labelRepository = $this->entityManager->getRepository(\Nkamuo\NotificationTrackerBundle\Entity\Label::class);
                foreach ($requestData['labels'] as $labelName) {
                    $label = $labelRepository->findOneBy(['name' => $labelName]);
                    if ($label) {
                        $message->addLabel($label);
                    }
                }
            }

            // Create content
            $content = new MessageContent();
            $content->setMessage($message);
            if (isset($requestData['text'])) {
                $content->setBodyText($requestData['text']);
            }
            if (isset($requestData['html'])) {
                $content->setBodyHtml($requestData['html']);
            }
            $message->setContent($content);

            // Handle recipients
            $recipients = is_array($requestData['to']) ? $requestData['to'] : [$requestData['to']];
            foreach ($recipients as $recipientData) {
                $recipient = new MessageRecipient();
                $recipient->setMessage($message);
                
                if (is_string($recipientData)) {
                    $recipient->setAddress($recipientData);
                } else {
                    $recipient->setAddress($recipientData['email']);
                    $recipient->setName($recipientData['name'] ?? null);
                }
                
                $recipient->setType(MessageRecipient::TYPE_TO);
                $recipient->setStatus(MessageRecipient::STATUS_PENDING);
                $message->addRecipient($recipient);
            }

            // Handle CC recipients
            if (isset($requestData['cc']) && is_array($requestData['cc'])) {
                foreach ($requestData['cc'] as $recipientData) {
                    $recipient = new MessageRecipient();
                    $recipient->setMessage($message);
                    
                    if (is_string($recipientData)) {
                        $recipient->setAddress($recipientData);
                    } else {
                        $recipient->setAddress($recipientData['email']);
                        $recipient->setName($recipientData['name'] ?? null);
                    }
                    
                    $recipient->setType(MessageRecipient::TYPE_CC);
                    $recipient->setStatus(MessageRecipient::STATUS_PENDING);
                    $message->addRecipient($recipient);
                }
            }

            // Handle BCC recipients
            if (isset($requestData['bcc']) && is_array($requestData['bcc'])) {
                foreach ($requestData['bcc'] as $recipientData) {
                    $recipient = new MessageRecipient();
                    $recipient->setMessage($message);
                    
                    if (is_string($recipientData)) {
                        $recipient->setAddress($recipientData);
                    } else {
                        $recipient->setAddress($recipientData['email']);
                        $recipient->setName($recipientData['name'] ?? null);
                    }
                    
                    $recipient->setType(MessageRecipient::TYPE_BCC);
                    $recipient->setStatus(MessageRecipient::STATUS_PENDING);
                    $message->addRecipient($recipient);
                }
            }

            // Save message first
            $this->entityManager->persist($message);
            $this->entityManager->flush();

            // Check if this should be saved as draft or sent immediately
            if (isset($requestData['save_as_draft']) && $requestData['save_as_draft']) {
                $message->setStatus($message::STATUS_PENDING);
                $this->entityManager->flush();
                
                return new JsonResponse([
                    'success' => true,
                    'message_id' => (string) $message->getId(),
                    'status' => 'saved_as_draft',
                ]);
            }

            // Schedule for later?
            if (isset($requestData['scheduled_at'])) {
                $scheduledAt = new \DateTimeImmutable($requestData['scheduled_at']);
                if ($scheduledAt <= new \DateTimeImmutable()) {
                    return new JsonResponse([
                        'error' => 'Scheduled time must be in the future'
                    ], 400);
                }
                
                $message->setScheduledAt($scheduledAt);
                $message->setStatus($message::STATUS_PENDING);
                $this->entityManager->flush();
                
                return new JsonResponse([
                    'success' => true,
                    'message_id' => (string) $message->getId(),
                    'status' => 'scheduled',
                    'scheduled_at' => $scheduledAt->format(\DateTime::ATOM),
                ]);
            }

            // Send immediately
            $email = (new Email())
                ->subject($requestData['subject'])
                ->from($requestData['from'] ?? 'noreply@example.com');

            // Add recipients
            foreach ($message->getRecipients() as $recipient) {
                switch ($recipient->getType()) {
                    case MessageRecipient::TYPE_TO:
                        $email->addTo($recipient->getAddress(), $recipient->getName());
                        break;
                    case MessageRecipient::TYPE_CC:
                        $email->addCc($recipient->getAddress(), $recipient->getName());
                        break;
                    case MessageRecipient::TYPE_BCC:
                        $email->addBcc($recipient->getAddress(), $recipient->getName());
                        break;
                }
            }

            if (isset($requestData['text'])) {
                $email->text($requestData['text']);
            }
            if (isset($requestData['html'])) {
                $email->html($requestData['html']);
            }

            // Set tracking header
            $email->getHeaders()->addTextHeader('X-Notification-Tracker-ID', (string) $message->getId());

            $this->mailer->send($email);

            $this->logger->info('Email sent successfully via API', [
                'message_id' => (string) $message->getId(),
                'subject' => $requestData['subject'],
                'recipients' => count($recipients),
            ]);

            return new JsonResponse([
                'success' => true,
                'message_id' => (string) $message->getId(),
                'status' => 'sent',
                'recipients_count' => count($recipients),
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to send email via API', [
                'error' => $e->getMessage(),
                'request_data' => $requestData,
            ]);

            return new JsonResponse([
                'error' => 'Failed to send email: ' . $e->getMessage()
            ], 500);
        }
    }
}
