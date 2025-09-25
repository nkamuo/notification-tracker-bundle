<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle;

use Nkamuo\NotificationTrackerBundle\DependencyInjection\Compiler\WebhookProviderPass;
use Nkamuo\NotificationTrackerBundle\DependencyInjection\NotificationTrackerExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * ⚠️ EXPERIMENTAL BUNDLE - DO NOT USE IN PRODUCTION
 * 
 * This is an experimental Symfony bundle for notification tracking.
 * APIs may change without notice. Use at your own risk.
 * 
 * @experimental
 */
class NotificationTrackerBundle extends AbstractBundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        
        $container->addCompilerPass(new WebhookProviderPass());
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        return new NotificationTrackerExtension();
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}