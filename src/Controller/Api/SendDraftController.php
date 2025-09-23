<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Controller\Api;

use Doctrine\ORM\EntityManagerInterface;
use Nkamuo\NotificationTrackerBundle\Entity\NotificationDraft;
use Nkamuo\NotificationTrackerBundle\Service\NotificationSender;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class SendDraftController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NotificationSender $notificationSender
    ) {
    }

    public function __invoke(NotificationDraft $data, Request $request): JsonResponse
    {
        if ($data->getStatus() !== NotificationDraft::STATUS_DRAFT) {
            return new JsonResponse([
                'error' => 'Only draft notifications can be sent immediately'
            ], 400);
        }

        try {
            // Send the notification
            $result = $this->notificationSender->sendDraft($data);
            
            $data->setStatus(NotificationDraft::STATUS_SENT);
            $data->setSentAt(new \DateTimeImmutable());
            
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'messages_created' => $result['messages_created'],
                'channels_used' => $result['channels_used'],
                'recipients_notified' => $result['recipients_notified'],
            ]);
            
        } catch (\Exception $e) {
            $data->setStatus(NotificationDraft::STATUS_FAILED);
            $data->setFailureReason($e->getMessage());
            
            $this->entityManager->flush();

            return new JsonResponse([
                'error' => 'Failed to send notification: ' . $e->getMessage()
            ], 500);
        }
    }
}
