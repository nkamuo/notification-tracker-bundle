<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Controller\Api;

use Doctrine\ORM\EntityManagerInterface;
use Nkamuo\NotificationTrackerBundle\Entity\Notification;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Psr\Log\LoggerInterface;

#[AsController]
class ScheduleNotificationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(Notification $notification, Request $request): JsonResponse
    {
        // Validate notification can be scheduled
        if (!in_array($notification->getStatus(), [Notification::STATUS_DRAFT, Notification::STATUS_SCHEDULED])) {
            return new JsonResponse([
                'error' => 'Only draft or scheduled notifications can be rescheduled'
            ], 400);
        }

        $requestData = json_decode($request->getContent(), true);
        
        if (!isset($requestData['scheduledAt'])) {
            return new JsonResponse([
                'error' => 'Field scheduledAt is required'
            ], 400);
        }

        try {
            $scheduledAt = new \DateTimeImmutable($requestData['scheduledAt']);
            
            if ($scheduledAt <= new \DateTimeImmutable()) {
                return new JsonResponse([
                    'error' => 'Scheduled time must be in the future'
                ], 400);
            }

            $notification->setScheduledAt($scheduledAt);
            $notification->setStatus(Notification::STATUS_SCHEDULED);
            $this->entityManager->flush();

            $this->logger->info('Notification scheduled', [
                'notification_id' => (string) $notification->getId(),
                'scheduled_at' => $scheduledAt->format(\DateTime::ATOM),
            ]);

            return new JsonResponse([
                'success' => true,
                'notification_id' => (string) $notification->getId(),
                'status' => $notification->getStatus(),
                'scheduled_at' => $scheduledAt->format(\DateTime::ATOM),
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to schedule notification', [
                'notification_id' => (string) $notification->getId(),
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'error' => 'Failed to schedule notification: ' . $e->getMessage()
            ], 500);
        }
    }
}
