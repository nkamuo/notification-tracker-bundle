<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Controller\Api;

use Nkamuo\NotificationTrackerBundle\Entity\Message;
use Nkamuo\NotificationTrackerBundle\Service\MessageRetryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class RetryMessageController extends AbstractController
{
    public function __construct(
        private readonly MessageRetryService $retryService
    ) {
    }

    public function __invoke(Message $message): JsonResponse
    {
        $result = $this->retryService->retryMessage($message);
        
        if ($result) {
            return new JsonResponse([
                'success' => true,
                'message' => 'Message queued for retry',
            ]);
        }
        
        return new JsonResponse([
            'success' => false,
            'message' => 'Failed to retry message',
        ], 400);
    }
}