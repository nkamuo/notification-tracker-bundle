<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Controller\Api;

use Doctrine\ORM\EntityManagerInterface;
use Nkamuo\NotificationTrackerBundle\Service\NotificationSender;
use Nkamuo\NotificationTrackerBundle\Service\MessageTracker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Psr\Log\LoggerInterface;

#[AsController]
class SendNotificationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NotificationSender $notificationSender,
        private readonly MessageTracker $messageTracker,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $requestData = json_decode($request->getContent(), true);
        
        // Validate required fields
        $requiredFields = ['channels', 'content'];
        foreach ($requiredFields as $field) {
            if (!isset($requestData[$field])) {
                return new JsonResponse([
                    'error' => "Field '{$field}' is required"
                ], 400);
            }
        }

        if (!is_array($requestData['channels']) || empty($requestData['channels'])) {
            return new JsonResponse([
                'error' => 'At least one channel must be specified'
            ], 400);
        }

        try {
            $results = [];
            $overallSuccess = true;

            // Check if this should be saved as draft or sent immediately
            if (isset($requestData['save_as_draft']) && $requestData['save_as_draft']) {
                // TODO: Create NotificationDraft and link to it
                return new JsonResponse([
                    'success' => true,
                    'status' => 'saved_as_draft',
                    'note' => 'Draft functionality will be implemented via NotificationDraft entity',
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
                
                // TODO: Create scheduled notification
                return new JsonResponse([
                    'success' => true,
                    'status' => 'scheduled',
                    'scheduled_at' => $scheduledAt->format(\DateTime::ATOM),
                    'note' => 'Scheduling functionality will be implemented via NotificationDraft entity',
                ]);
            }

            // Send to each channel
            foreach ($requestData['channels'] as $channelConfig) {
                if (!isset($channelConfig['type'])) {
                    $results[] = [
                        'channel' => 'unknown',
                        'success' => false,
                        'error' => 'Channel type is required'
                    ];
                    $overallSuccess = false;
                    continue;
                }

                try {
                    $channelType = $channelConfig['type'];
                    $channelContent = array_merge($requestData['content'], $channelConfig);
                    
                    // Add global labels if provided
                    if (isset($requestData['labels'])) {
                        $channelContent['labels'] = array_merge(
                            $channelContent['labels'] ?? [],
                            $requestData['labels']
                        );
                    }

                    $result = $this->sendToChannel($channelType, $channelContent);
                    $results[] = array_merge($result, ['channel' => $channelType]);
                    
                    if (!$result['success']) {
                        $overallSuccess = false;
                    }

                } catch (\Exception $e) {
                    $this->logger->error('Failed to send to channel', [
                        'channel_type' => $channelConfig['type'] ?? 'unknown',
                        'error' => $e->getMessage(),
                        'channel_config' => $channelConfig,
                    ]);

                    $results[] = [
                        'channel' => $channelConfig['type'] ?? 'unknown',
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                    $overallSuccess = false;
                }
            }

            $this->logger->info('Multi-channel notification sending completed', [
                'channels_count' => count($requestData['channels']),
                'overall_success' => $overallSuccess,
                'results' => $results,
            ]);

            return new JsonResponse([
                'success' => $overallSuccess,
                'status' => $overallSuccess ? 'sent' : 'partially_failed',
                'channels' => $results,
                'summary' => [
                    'total' => count($results),
                    'successful' => count(array_filter($results, fn($r) => $r['success'])),
                    'failed' => count(array_filter($results, fn($r) => !$r['success'])),
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to process multi-channel notification', [
                'error' => $e->getMessage(),
                'request_data' => $requestData,
            ]);

            return new JsonResponse([
                'error' => 'Failed to send notification: ' . $e->getMessage()
            ], 500);
        }
    }

    private function sendToChannel(string $channelType, array $content): array
    {
        switch (strtolower($channelType)) {
            case 'email':
                return $this->sendEmail($content);
            case 'sms':
                return $this->sendSms($content);
            case 'slack':
                return $this->sendSlack($content);
            default:
                return [
                    'success' => false,
                    'error' => "Unsupported channel type: {$channelType}"
                ];
        }
    }

    private function sendEmail(array $content): array
    {
        try {
            $controller = new SendEmailController(
                $this->entityManager,
                $this->container->get('mailer'),
                $this->messageTracker,
                $this->logger
            );

            $request = new Request([], [], [], [], [], [], json_encode($content));
            $response = $controller($request);
            
            $responseData = json_decode($response->getContent(), true);
            return [
                'success' => $responseData['success'] ?? false,
                'message_id' => $responseData['message_id'] ?? null,
                'status' => $responseData['status'] ?? 'unknown',
                'error' => $responseData['error'] ?? null,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function sendSms(array $content): array
    {
        try {
            $controller = new SendSmsController(
                $this->entityManager,
                $this->messageTracker,
                $this->logger
            );

            $request = new Request([], [], [], [], [], [], json_encode($content));
            $response = $controller($request);
            
            $responseData = json_decode($response->getContent(), true);
            return [
                'success' => $responseData['success'] ?? false,
                'message_id' => $responseData['message_id'] ?? null,
                'status' => $responseData['status'] ?? 'unknown',
                'error' => $responseData['error'] ?? null,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function sendSlack(array $content): array
    {
        try {
            $controller = new SendSlackController(
                $this->entityManager,
                $this->messageTracker,
                $this->logger
            );

            $request = new Request([], [], [], [], [], [], json_encode($content));
            $response = $controller($request);
            
            $responseData = json_decode($response->getContent(), true);
            return [
                'success' => $responseData['success'] ?? false,
                'message_id' => $responseData['message_id'] ?? null,
                'status' => $responseData['status'] ?? 'unknown',
                'error' => $responseData['error'] ?? null,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
