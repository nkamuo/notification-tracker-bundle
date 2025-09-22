<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\DependencyInjection\Compiler;

use Nkamuo\NotificationTrackerBundle\Service\WebhookProviderRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class WebhookProviderPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(WebhookProviderRegistry::class)) {
            return;
        }

        $definition = $container->findDefinition(WebhookProviderRegistry::class);
        $taggedServices = $container->findTaggedServiceIds('notification_tracker.webhook_provider');

        foreach ($taggedServices as $id => $tags) {
            $definition->addMethodCall('addProvider', [new Reference($id)]);
        }
    }
}