<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Controller\Api;

use Nkamuo\NotificationTrackerBundle\Entity\Message;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class CancelMessageController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function __invoke(Message $message): JsonResponse
    {
        if ($message->getStatus() === Message::STATUS_SENT) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Cannot cancel already sent message',
            ], 400);
        }
        
        $message->setStatus(Message::STATUS_CANCELLED);
        $this->entityManager->flush();
        
        return new JsonResponse([
            'success' => true,
            'message' => 'Message cancelled',
        ]);
    }
}