<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class NotificationTrackerExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);
        
        // Set parameters with namespace
        $this->setParameters($container, $config);
        
        // Load services
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        
        // Always load core services
        $loader->load('services.yaml');
        
        if ($config['enabled']) {
            $loader->load('event_subscribers.yaml');
            $loader->load('message_handlers.yaml');
            $loader->load('commands.yaml');
            
            if ($config['tracking']['enabled']) {
                $loader->load('tracking.yaml');
            }
            
            if ($config['webhooks']['enabled']) {
                $loader->load('webhooks.yaml');
                $this->configureWebhookProviders($container, $config['webhooks']['providers']);
            }
            
            if ($config['api']['enabled']) {
                $loader->load('api_platform.yaml');
            }
            
            if ($config['analytics']['enabled']) {
                $loader->load('analytics.yaml');
            }
            
            if ($config['templates']['enabled']) {
                $loader->load('templates.yaml');
            }
        }
    }
    
    public function prepend(ContainerBuilder $container): void
    {
        // Prepend doctrine configuration
        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'NotificationTrackerBundle' => [
                        'type' => 'attribute',
                        'dir' => '%kernel.project_dir%/vendor/nkamuo/notification-tracker-bundle/src/Entity',
                        'prefix' => 'Nkamuo\NotificationTrackerBundle\Entity',
                        'alias' => 'NotificationTracker',
                    ],
                ],
            ],
        ]);
        
        // Prepend API Platform configuration if enabled
        if ($container->hasExtension('api_platform')) {
            $container->prependExtensionConfig('api_platform', [
                'mapping' => [
                    'paths' => [
                        '%kernel.project_dir%/vendor/nkamuo/notification-tracker-bundle/src/Entity',
                    ],
                ],
            ]);
        }
    }
    
    private function setParameters(ContainerBuilder $container, array $config): void
    {
        // Main parameter
        $container->setParameter('notification_tracker.enabled', $config['enabled']);
        
        // Tracking parameters
        foreach ($config['tracking'] as $key => $value) {
            $container->setParameter("notification_tracker.tracking.{$key}", $value);
        }
        
        // Storage parameters
        foreach ($config['storage'] as $key => $value) {
            $container->setParameter("notification_tracker.storage.{$key}", $value);
        }
        
        // Retry parameters
        foreach ($config['retry'] as $key => $value) {
            $container->setParameter("notification_tracker.retry.{$key}", $value);
        }
        
        // Webhook parameters
        foreach ($config['webhooks'] as $key => $value) {
            if ($key !== 'providers') {
                $container->setParameter("notification_tracker.webhooks.{$key}", $value);
            }
        }
        
        // Channel parameters
        $container->setParameter('notification_tracker.channels', $config['channels']);
        
        // Template parameters
        foreach ($config['templates'] as $key => $value) {
            $container->setParameter("notification_tracker.templates.{$key}", $value);
        }
        
        // API parameters
        foreach ($config['api'] as $key => $value) {
            $container->setParameter("notification_tracker.api.{$key}", $value);
        }
        
        // Analytics parameters
        foreach ($config['analytics'] as $key => $value) {
            $container->setParameter("notification_tracker.analytics.{$key}", $value);
        }
        
        // Messenger parameters
        foreach ($config['messenger'] as $key => $value) {
            $container->setParameter("notification_tracker.messenger.{$key}", $value);
        }
    }
    
    private function configureWebhookProviders(ContainerBuilder $container, array $providers): void
    {
        foreach ($providers as $name => $config) {
            $container->setParameter("notification_tracker.webhook_provider.{$name}", $config);
        }
    }
}