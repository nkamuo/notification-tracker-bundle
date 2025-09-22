<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Controller\Api;

use Doctrine\ORM\EntityManagerInterface;
use Nkamuo\NotificationTrackerBundle\Entity\Notification;
use Nkamuo\NotificationTrackerBundle\Service\EventEnrichmentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class SendNotificationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventEnrichmentService $enrichmentService
    ) {
    }

    public function __invoke(Notification $data, Request $request): JsonResponse
    {
        if ($data->getStatus() !== Notification::STATUS_DRAFT) {
            return new JsonResponse([
                'error' => 'Only draft notifications can be sent',
                'current_status' => $data->getStatus()
            ], 400);
        }

        // Dispatch pre-send event for enrichment and validation
        $context = ['source' => 'api_controller', 'request_data' => $request->getContent()];
        $shouldSend = $this->enrichmentService->enrichNotificationPreSend($data, $context);
        
        if (!$shouldSend) {
            return new JsonResponse([
                'error' => 'Notification sending was cancelled by pre-send event',
                'notification_id' => $data->getId()->toRfc4122()
            ], 400);
        }

        try {
            // Update status to sent
            $data->setStatus(Notification::STATUS_SENT);
            $data->setSentAt(new \DateTimeImmutable());
            
            $this->entityManager->flush();

            // Dispatch post-send success event
            $this->enrichmentService->processNotificationPostSend($data, true, null, $context);

            return new JsonResponse([
                'success' => true,
                'notification_id' => $data->getId()->toRfc4122(),
                'status' => $data->getStatus(),
                'sent_at' => $data->getSentAt()?->format('c'),
                'message_count' => $data->getTotalMessages()
            ]);

        } catch (\Exception $e) {
            $data->setStatus(Notification::STATUS_FAILED);
            $data->addMetadata('failure_reason', $e->getMessage());
            $this->entityManager->flush();

            // Dispatch post-send failure event
            $this->enrichmentService->processNotificationPostSend($data, false, $e->getMessage(), $context);

            return new JsonResponse([
                'error' => 'Failed to send notification',
                'message' => $e->getMessage(),
                'notification_id' => $data->getId()->toRfc4122()
            ], 500);
        }
    }
}