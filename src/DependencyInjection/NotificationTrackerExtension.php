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
            // Load optional service files with existence checks
            $configFiles = [
                'event_subscribers.yaml',
                'message_handlers.yaml', 
                'commands.yaml'
            ];
            
            foreach ($configFiles as $file) {
                if (file_exists(__DIR__ . '/../Resources/config/' . $file)) {
                    $loader->load($file);
                }
            }
            
            if ($config['tracking']['enabled'] && file_exists(__DIR__ . '/../Resources/config/tracking.yaml')) {
                $loader->load('tracking.yaml');
            }
            
            if ($config['webhooks']['enabled'] && file_exists(__DIR__ . '/../Resources/config/webhooks.yaml')) {
                $loader->load('webhooks.yaml');
                $this->configureWebhookProviders($container, $config['webhooks']['providers']);
            }
            
            if ($config['api']['enabled'] && file_exists(__DIR__ . '/../Resources/config/api_platform.yaml')) {
                $loader->load('api_platform.yaml');
            }
            
            if ($config['analytics']['enabled'] && file_exists(__DIR__ . '/../Resources/config/analytics.yaml')) {
                $loader->load('analytics.yaml');
            }
            
            if ($config['templates']['enabled'] && file_exists(__DIR__ . '/../Resources/config/templates.yaml')) {
                $loader->load('templates.yaml');
            }
        }
    }
    
    public function prepend(ContainerBuilder $container): void
    {
        // Detect if we're running from the bundle itself (for development/testing)
        $kernelProjectDir = $container->getParameter('kernel.project_dir');
        $bundleDir = dirname(__DIR__, 2); // Go up from src/DependencyInjection to bundle root
        
        // If we're running from the bundle itself, use a different entity path
        if (realpath($kernelProjectDir) === realpath($bundleDir)) {
            // We're in development/test mode - don't prepend doctrine config
            // as it will be handled by the test kernel
        } else {
            // We're installed as a vendor package
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
        }
        
        // Prepend API Platform configuration if enabled
        if ($container->hasExtension('api_platform')) {
            $entityPath = (realpath($kernelProjectDir) === realpath($bundleDir)) 
                ? '%kernel.project_dir%/src/Entity'
                : '%kernel.project_dir%/vendor/nkamuo/notification-tracker-bundle/src/Entity';
                
            $container->prependExtensionConfig('api_platform', [
                'mapping' => [
                    'paths' => [
                        $entityPath,
                    ],
                ],
            ]);
        }
        
        // Auto-configure Messenger transports for notifications
        $this->prependMessengerConfiguration($container);
    }
    
    private function prependMessengerConfiguration(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('framework')) {
            return;
        }
        
        // Get our bundle configuration to check if auto-configuration is enabled
        $configs = $container->getExtensionConfig($this->getAlias());
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);
        
        if (!$config['messenger']['enabled'] || !$config['messenger']['auto_configure']) {
            return;
        }
        
        // Prepare messenger transport configuration
        $messengerConfig = [
            'transports' => [],
        ];
        
        // Configure main notification transport
        if ($config['messenger']['transports']['notification']['enabled']) {
            $transportConfig = $config['messenger']['transports']['notification'];
            $dsn = $this->buildTransportDsn($transportConfig);
            $messengerConfig['transports']['notification'] = $dsn;
        }
        
        // Configure email-specific transport if enabled
        if ($config['messenger']['transports']['notification_email']['enabled']) {
            $transportConfig = $config['messenger']['transports']['notification_email'];
            $dsn = $this->buildTransportDsn($transportConfig);
            $messengerConfig['transports']['notification_email'] = $dsn;
        }
        
        // Configure routing for auto-configured channels
        $routing = [];
        $autoConfigureChannels = $config['messenger']['auto_configure_channels'];
        
        if ($autoConfigureChannels['email']) {
            $routing['Symfony\\Component\\Mailer\\Messenger\\SendEmailMessage'] = 
                $config['messenger']['transports']['notification_email']['enabled'] ? 'notification_email' : 'notification';
        }
        
        if ($autoConfigureChannels['sms'] || $autoConfigureChannels['push'] || 
            $autoConfigureChannels['slack'] || $autoConfigureChannels['telegram']) {
            // Route notification messages to our transport
            $routing['Symfony\\Component\\Notifier\\Message\\MessageInterface'] = 'notification';
        }
        
        if (!empty($routing)) {
            $messengerConfig['routing'] = $routing;
        }
        
        // Only prepend if we have transports to configure
        if (!empty($messengerConfig['transports'])) {
            $container->prependExtensionConfig('framework', [
                'messenger' => $messengerConfig
            ]);
        }
    }
    
    private function buildTransportDsn(array $config): string
    {
        $dsn = $config['dsn'];
        
        // Build query parameters
        $params = [];
        
        if ($config['transport_name'] !== 'notification') {
            $params['transport_name'] = $config['transport_name'];
        }
        
        if ($config['queue_name'] !== 'default') {
            $params['queue_name'] = $config['queue_name'];
        }
        
        if (!$config['analytics_enabled']) {
            $params['analytics_enabled'] = 'false';
        }
        
        if ($config['provider_aware_routing']) {
            $params['provider_aware_routing'] = 'true';
        }
        
        if ($config['batch_size'] !== 10) {
            $params['batch_size'] = (string)$config['batch_size'];
        }
        
        if ($config['max_retries'] !== 3) {
            $params['max_retries'] = (string)$config['max_retries'];
        }
        
        if ($config['retry_delays'] !== ['1000', '5000', '30000']) {
            $params['retry_delays'] = implode(',', $config['retry_delays']);
        }
        
        // Add query parameters to DSN
        if (!empty($params)) {
            $separator = strpos($dsn, '?') !== false ? '&' : '?';
            $dsn .= $separator . http_build_query($params);
        }
        
        return $dsn;
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
        if (isset($config['channels'])) {
            $container->setParameter('notification_tracker.channels', $config['channels']);
        }
        
        // Template parameters
        if (isset($config['templates'])) {
            foreach ($config['templates'] as $key => $value) {
                $container->setParameter("notification_tracker.templates.{$key}", $value);
            }
        }
        
        // API parameters
        if (isset($config['api'])) {
            foreach ($config['api'] as $key => $value) {
                $container->setParameter("notification_tracker.api.{$key}", $value);
            }
        }
        
        // Analytics parameters
        if (isset($config['analytics'])) {
            foreach ($config['analytics'] as $key => $value) {
                $container->setParameter("notification_tracker.analytics.{$key}", $value);
            }
        }
        
        // Messenger parameters
        if (isset($config['messenger'])) {
            foreach ($config['messenger'] as $key => $value) {
                $container->setParameter("notification_tracker.messenger.{$key}", $value);
            }
        }
    }
    
    private function configureWebhookProviders(ContainerBuilder $container, array $providers): void
    {
        foreach ($providers as $name => $config) {
            $container->setParameter("notification_tracker.webhook_provider.{$name}", $config);
        }
    }
}