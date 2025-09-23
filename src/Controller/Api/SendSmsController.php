<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Controller\Api;

use Doctrine\ORM\EntityManagerInterface;
use Nkamuo\NotificationTrackerBundle\Entity\SmsMessage;
use Nkamuo\NotificationTrackerBundle\Entity\MessageContent;
use Nkamuo\NotificationTrackerBundle\Entity\MessageRecipient;
use Nkamuo\NotificationTrackerBundle\Enum\NotificationDirection;
use Nkamuo\NotificationTrackerBundle\Enum\MessageStatus;
use Nkamuo\NotificationTrackerBundle\Service\MessageTracker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Psr\Log\LoggerInterface;

#[AsController]
class SendSmsController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageTracker $messageTracker,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $requestData = json_decode($request->getContent(), true);
        
        // Validate required fields
        $requiredFields = ['to', 'message'];
        foreach ($requiredFields as $field) {
            if (!isset($requestData[$field])) {
                return new JsonResponse([
                    'error' => "Field '{$field}' is required"
                ], 400);
            }
        }

        try {
            // Create tracked message
            $message = new SmsMessage();
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
            $content->setBodyText($requestData['message']);
            $message->setContent($content);

            // Handle recipients (SMS can have multiple recipients)
            $recipients = is_array($requestData['to']) ? $requestData['to'] : [$requestData['to']];
            foreach ($recipients as $phoneNumber) {
                $recipient = new MessageRecipient();
                $recipient->setMessage($message);
                $recipient->setAddress($phoneNumber);
                $recipient->setType(MessageRecipient::TYPE_TO);
                $recipient->setStatus(MessageRecipient::STATUS_PENDING);
                $message->addRecipient($recipient);
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

            // For now, mark as sent immediately
            // TODO: Integrate with actual SMS service (Twilio, AWS SNS, etc.)
            $message->setStatus($message::STATUS_SENT);
            $this->entityManager->flush();

            $this->logger->info('SMS prepared for sending via API', [
                'message_id' => (string) $message->getId(),
                'recipients_count' => count($recipients),
                'message_preview' => substr($requestData['message'], 0, 50) . '...',
            ]);

            return new JsonResponse([
                'success' => true,
                'message_id' => (string) $message->getId(),
                'status' => 'sent',
                'recipients' => [
                    'total' => count($recipients),
                    'successful' => count($recipients),
                    'failed' => 0,
                ],
                'note' => 'SMS tracking recorded. Integrate with your SMS provider for actual delivery.',
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to process SMS via API', [
                'error' => $e->getMessage(),
                'request_data' => $requestData,
            ]);

            return new JsonResponse([
                'error' => 'Failed to send SMS: ' . $e->getMessage()
            ], 500);
        }
    }
}
