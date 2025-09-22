<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Controller;

use Nkamuo\NotificationTrackerBundle\Service\WebhookProcessor;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/webhooks/notification-tracker')]
class WebhookController extends AbstractController
{
    public function __construct(
        private readonly WebhookProcessor $webhookProcessor,
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route('/{provider}', name: 'notification_tracker_webhook', methods: ['POST'])]
    public function webhook(Request $request, string $provider): Response
    {
        return $this->processWebhook($request, $provider);
    }

    #[Route('/{provider}/{endpointId}', name: 'notification_tracker_webhook_endpoint', methods: ['POST'])]
    public function webhookWithEndpoint(Request $request, string $provider, string $endpointId): Response
    {
        return $this->processWebhook($request, $provider, $endpointId);
    }

    private function processWebhook(Request $request, string $provider, ?string $endpointId = null): Response
    {
        try {
            $payload = $this->getPayload($request);
            $headers = $this->getHeaders($request);

            $this->logger->info('Webhook received', [
                'provider' => $provider,
                'endpoint_id' => $endpointId,
                'headers' => array_keys($headers),
                'payload_size' => count($payload),
            ]);

            $webhookPayload = $this->webhookProcessor->processWebhook(
                $provider,
                $payload,
                $headers,
                $endpointId
            );

            return new JsonResponse([
                'success' => true,
                'webhook_id' => (string) $webhookPayload->getId(),
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Webhook processing failed', [
                'provider' => $provider,
                'endpoint_id' => $endpointId,
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'success' => false,
                'error' => 'Webhook processing failed',
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    private function getPayload(Request $request): array
    {
        $content = $request->getContent();
        
        if ($request->getContentTypeFormat() === 'json') {
            return json_decode($content, true) ?? [];
        }
        
        parse_str($content, $payload);
        return $payload;
    }

    private function getHeaders(Request $request): array
    {
        return [
            'signature' => $request->headers->get('X-Webhook-Signature'),
            'timestamp' => $request->headers->get('X-Webhook-Timestamp'),
            'content-type' => $request->headers->get('Content-Type'),
            // Provider specific headers
            'X-Twilio-Signature' => $request->headers->get('X-Twilio-Signature'),
            'X-Twilio-Email-Event-Webhook-Signature' => $request->headers->get('X-Twilio-Email-Event-Webhook-Signature'),
            'X-Twilio-Email-Event-Webhook-Timestamp' => $request->headers->get('X-Twilio-Email-Event-Webhook-Timestamp'),
            'X-Mailgun-Signature' => $request->headers->get('X-Mailgun-Signature'),
            'X-Mailgun-Timestamp' => $request->headers->get('X-Mailgun-Timestamp'),
            'X-Mailgun-Token' => $request->headers->get('X-Mailgun-Token'),
            'X-SendGrid-Signature' => $request->headers->get('X-SendGrid-Signature'),
            'X-Mailchimp-Signature' => $request->headers->get('X-Mailchimp-Signature'),
        ];
    }
}