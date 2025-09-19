<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Tests\Functional;

use Nkamuo\NotificationTrackerBundle\Service\MessageTracker;
use Nkamuo\NotificationTrackerBundle\Service\NotificationTracker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SimpleServiceTest extends TestCase
{
    private ContainerInterface $container;

    protected function setUp(): void
    {
        $kernel = new TestKernel('test', true);
        $kernel->boot();
        $this->container = $kernel->getContainer();
    }

    public function testMessageTrackerService(): void
    {
        $this->assertTrue($this->container->has(MessageTracker::class));
        
        $messageTracker = $this->container->get(MessageTracker::class);
        $this->assertInstanceOf(MessageTracker::class, $messageTracker);
    }

    public function testNotificationTrackerService(): void
    {
        $this->assertTrue($this->container->has(NotificationTracker::class));
        
        $notificationTracker = $this->container->get(NotificationTracker::class);
        $this->assertInstanceOf(NotificationTracker::class, $notificationTracker);
    }

    public function testServiceContainer(): void
    {
        $this->assertNotNull($this->container);
        $this->assertTrue($this->container->has('doctrine.orm.entity_manager'));
    }
}
