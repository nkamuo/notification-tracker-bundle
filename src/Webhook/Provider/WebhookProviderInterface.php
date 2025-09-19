<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Webhook\Provider;

interface WebhookProviderInterface
{
    public function supports(string $provider): bool;
    
    public function verifySignature(array $payload, array $headers): bool;
    
    public function parseWebhook(array $payload): array;
}