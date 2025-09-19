# Webhook Controllers

## Base Webhook Controller

```php
<?php
// src/Controller/Webhook/BaseWebhookController.php

namespace App\Controller\Webhook;

use App\Service\Communication\WebhookProcessor;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

abstract class BaseWebhookController extends AbstractController
{
    public function __construct(
        protected WebhookProcessor $webhookProcessor,
        protected LoggerInterface $logger
    ) {}

    protected function processWebhook(Request $request, string $provider): Response
    {
        try {
            $payload = $this->getPayload($request);
            $headers = $this->getHeaders($request);

            $this->logger->info('Webhook received', [
                'provider' => $provider,
                'headers' => $headers,
                'payload_sample' => array_slice($payload, 0, 3),
            ]);

            $webhookPayload = $this->webhookProcessor->processWebhook(
                $provider,
                $payload,
                $headers
            );

            return new JsonResponse([
                'success' => true,
                'webhook_id' => $webhookPayload->getId(),
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Webhook processing failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse([
                'success' => false,
                'error' => 'Webhook processing failed',
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    protected function getPayload(Request $request): array
    {
        $content = $request->getContent();
        
        if ($request->getContentType() === 'json') {
            return json_decode($content, true) ?? [];
        }
        
        parse_str($content, $payload);
        return $payload;
    }

    protected function getHeaders(Request $request): array
    {
        return [
            'signature' => $request->headers->get('X-Webhook-Signature'),
            'timestamp' => $request->headers->get('X-Webhook-Timestamp'),
            'X-Twilio-Signature' => $request->headers->get('X-Twilio-Signature'),
            'X-Twilio-Email-Event-Webhook-Signature' => $request->headers->get('X-Twilio-Email-Event-Webhook-Signature'),
            'X-Twilio-Email-Event-Webhook-Timestamp' => $request->headers->get('X-Twilio-Email-Event-Webhook-Timestamp'),
            'X-Mailgun-Signature' => $request->headers->get('X-Mailgun-Signature'),
            'X-Mailgun-Timestamp' => $request->headers->get('X-Mailgun-Timestamp'),
            'X-Mailgun-Token' => $request->headers->get('X-Mailgun-Token'),
        ];
    }
}
```

## Provider-Specific Controllers

```php
<?php
// src/Controller/Webhook/SendGridWebhookController.php

namespace App\Controller\Webhook;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/webhook/sendgrid', name: 'webhook_sendgrid')]
class SendGridWebhookController extends BaseWebhookController
{
    public function __invoke(Request $request): Response
    {
        return $this->processWebhook($request, 'sendgrid');
    }
}
```

```php
<?php
// src/Controller/Webhook/TwilioWebhookController.php

namespace App\Controller\Webhook;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/webhook/twilio', name: 'webhook_twilio')]
class TwilioWebhookController extends BaseWebhookController
{
    public function __invoke(Request $request): Response
    {
        return $this->processWebhook($request, 'twilio');
    }
}
```

```php
<?php
// src/Controller/Webhook/MailgunWebhookController.php

namespace App\Controller\Webhook;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/webhook/mailgun', name: 'webhook_mailgun')]
class MailgunWebhookController extends BaseWebhookController
{
    public function __invoke(Request $request): Response
    {
        return $this->processWebhook($request, 'mailgun');
    }
}
```

## Webhook Security Middleware

```php
<?php
// src/EventListener/WebhookSecurityListener.php

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class WebhookSecurityListener
{
    private array $allowedIpRanges = [
        'sendgrid' => [
            '168.245.0.0/17',
            '167.89.0.0/17',
        ],
        'twilio' => [
            '54.172.60.0/23',
            '54.244.51.0/24',
        ],
        'mailgun' => [
            '18.235.0.0/16',
            '52.217.0.0/16',
        ],
    ];

    public function __construct(
        private LoggerInterface $logger
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        
        // Check if this is a webhook endpoint
        if (!str_starts_with($request->getPathInfo(), '/webhook/')) {
            return;
        }

        $clientIp = $request->getClientIp();
        $provider = $this->extractProvider($request->getPathInfo());

        if (!$this->isIpAllowed($clientIp, $provider)) {
            $this->logger->warning('Webhook request from unauthorized IP', [
                'ip' => $clientIp,
                'provider' => $provider,
            ]);

            $event->setResponse(new JsonResponse([
                'error' => 'Unauthorized',
            ], Response::HTTP_FORBIDDEN));
        }
    }

    private function extractProvider(string $path): string
    {
        $parts = explode('/', trim($path, '/'));
        return $parts[1] ?? '';
    }

    private function isIpAllowed(string $ip, string $provider): bool
    {
        if (!isset($this->allowedIpRanges[$provider])) {
            return false;
        }

        foreach ($this->allowedIpRanges[$provider] as $range) {
            if ($this->ipInRange($ip, $range)) {
                return true;
            }
        }

        return false;
    }

    private function ipInRange(string $ip, string $range): bool
    {
        list($subnet, $bits) = explode('/', $range);
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        $subnet &= $mask;
        return ($ip & $mask) == $subnet;
    }
}
```