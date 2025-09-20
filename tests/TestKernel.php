<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nkamuo\NotificationTrackerBundle\NotificationTrackerBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class TestKernel extends BaseKernel
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
        $loader->load(__DIR__ . '/config/test.yaml');

        // Register test services
        $container->autowire('test.notification_analytics_collector', 'Nkamuo\NotificationTrackerBundle\Service\NotificationAnalyticsCollector')
            ->setPublic(true);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        // No routes needed for transport tests
    }

    public function getProjectDir(): string
    {
        return dirname(__DIR__);
    }
}
