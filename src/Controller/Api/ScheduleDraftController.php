<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Controller\Api;

use Doctrine\ORM\EntityManagerInterface;
use Nkamuo\NotificationTrackerBundle\Entity\NotificationDraft;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class ScheduleDraftController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function __invoke(NotificationDraft $data, Request $request): JsonResponse
    {
        if (!in_array($data->getStatus(), [NotificationDraft::STATUS_DRAFT, NotificationDraft::STATUS_SCHEDULED])) {
            return new JsonResponse([
                'error' => 'Only draft or already scheduled notifications can be scheduled'
            ], 400);
        }

        $requestData = json_decode($request->getContent(), true);
        
        if (!isset($requestData['scheduled_at'])) {
            return new JsonResponse([
                'error' => 'scheduled_at field is required'
            ], 400);
        }

        try {
            $scheduledAt = new \DateTimeImmutable($requestData['scheduled_at']);
            
            if ($scheduledAt <= new \DateTimeImmutable()) {
                return new JsonResponse([
                    'error' => 'Scheduled time must be in the future'
                ], 400);
            }

            $data->setScheduledAt($scheduledAt);
            $data->setStatus(NotificationDraft::STATUS_SCHEDULED);
            
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'scheduled_at' => $scheduledAt->format(\DateTime::ATOM),
                'message' => 'Notification scheduled successfully',
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Invalid scheduled_at format: ' . $e->getMessage()
            ], 400);
        }
    }
}
