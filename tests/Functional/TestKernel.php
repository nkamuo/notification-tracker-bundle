<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Tests\Functional;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nkamuo\NotificationTrackerBundle\NotificationTrackerBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new DoctrineBundle(),
            new NotificationTrackerBundle(),
        ];
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->loadFromExtension('framework', [
            'test' => true,
            'secret' => 'test-secret',
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'property_access' => null,
            'serializer' => null,
            'messenger' => [
                'transports' => [
                    'async' => 'in-memory://',
                ],
                'routing' => [
                    'Nkamuo\NotificationTrackerBundle\Message\*' => 'async',
                ],
            ],
        ]);

        $container->loadFromExtension('doctrine', [
            'dbal' => [
                'url' => 'sqlite:///:memory:',
                'driver' => 'pdo_sqlite',
            ],
            'orm' => [
                'auto_generate_proxy_classes' => true,
                'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware',
                'auto_mapping' => true,
                'mappings' => [
                    'NotificationTracker' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => '%kernel.project_dir%/src/Entity',
                        'prefix' => 'Nkamuo\NotificationTrackerBundle\Entity',
                        'alias' => 'NotificationTracker',
                    ],
                ],
            ],
        ]);

        // Simplified notification tracker configuration for testing
        $container->loadFromExtension('notification_tracker', [
            'enabled' => true,
            'tracking' => [
                'enabled' => true,
                'track_opens' => true,
                'track_clicks' => true,
                'store_content' => true,
            ],
            'storage' => [
                'attachment_directory' => sys_get_temp_dir() . '/test-attachments',
            ],
            'webhooks' => [
                'enabled' => true,
                'async_processing' => false, // Sync for testing
            ],
        ]);

        // Load test services configuration after extensions are loaded
        $loader->load(__DIR__ . '/../config/test_services.yaml');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        // Add any test routes if needed
    }
}
