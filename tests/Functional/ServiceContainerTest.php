<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Tests\Functional;

use Nkamuo\NotificationTrackerBundle\Service\AttachmentManager;
use Nkamuo\NotificationTrackerBundle\Service\MessageRetryService;
use Nkamuo\NotificationTrackerBundle\Service\MessageTracker;
use Nkamuo\NotificationTrackerBundle\Service\NotificationTracker;
use Nkamuo\NotificationTrackerBundle\Service\WebhookProcessor;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ServiceContainerTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel(['environment' => 'test']);
    }

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testServiceContainerCompiles(): void
    {
        $container = static::getContainer();
        
        // Test that the container compiles without errors
        $this->assertTrue($container->isCompiled());
    }

    public function testCoreServicesAreAvailable(): void
    {
        $container = static::getContainer();
        
        // Test that all core services can be retrieved
        $messageTracker = $container->get(MessageTracker::class);
        $this->assertInstanceOf(MessageTracker::class, $messageTracker);
        
        $notificationTracker = $container->get(NotificationTracker::class);
        $this->assertInstanceOf(NotificationTracker::class, $notificationTracker);
        
        $attachmentManager = $container->get(AttachmentManager::class);
        $this->assertInstanceOf(AttachmentManager::class, $attachmentManager);
        
        $messageRetryService = $container->get(MessageRetryService::class);
        $this->assertInstanceOf(MessageRetryService::class, $messageRetryService);
        
        $webhookProcessor = $container->get(WebhookProcessor::class);
        $this->assertInstanceOf(WebhookProcessor::class, $webhookProcessor);
    }

    public function testRepositoriesAreAvailable(): void
    {
        $container = static::getContainer();
        
        // Test that repositories are available
        $messageRepository = $container->get('Nkamuo\NotificationTrackerBundle\Repository\MessageRepository');
        $this->assertInstanceOf(\Nkamuo\NotificationTrackerBundle\Repository\MessageRepository::class, $messageRepository);
        
        $emailMessageRepository = $container->get('Nkamuo\NotificationTrackerBundle\Repository\EmailMessageRepository');
        $this->assertInstanceOf(\Nkamuo\NotificationTrackerBundle\Repository\EmailMessageRepository::class, $emailMessageRepository);
    }

    public function testBundleConfigurationIsLoaded(): void
    {
        $container = static::getContainer();
        
        // Test that bundle parameters are set correctly
        $this->assertTrue($container->getParameter('notification_tracker.enabled'));
        $this->assertTrue($container->getParameter('notification_tracker.tracking.enabled'));
        $this->assertTrue($container->getParameter('notification_tracker.tracking.track_opens'));
        $this->assertTrue($container->getParameter('notification_tracker.tracking.store_content'));
        $this->assertTrue($container->getParameter('notification_tracker.webhooks.enabled'));
        $this->assertFalse($container->getParameter('notification_tracker.webhooks.async_processing')); // We set to false for testing
    }

    public function testMessageHandlersAreRegistered(): void
    {
        $container = static::getContainer();
        
        // Test that message handlers are registered
        $this->assertTrue($container->has('Nkamuo\NotificationTrackerBundle\MessageHandler\TrackEmailMessageHandler'));
        $this->assertTrue($container->has('Nkamuo\NotificationTrackerBundle\MessageHandler\ProcessWebhookMessageHandler'));
        $this->assertTrue($container->has('Nkamuo\NotificationTrackerBundle\MessageHandler\RetryFailedMessageHandler'));
    }

    public function testEventSubscribersAreRegistered(): void
    {
        $container = static::getContainer();
        
        // Test that event subscribers are registered
        $this->assertTrue($container->has('Nkamuo\NotificationTrackerBundle\EventSubscriber\MailerEventSubscriber'));
    }

    public function testCommandsAreRegistered(): void
    {
        $container = static::getContainer();
        
        // Test that commands are registered
        $this->assertTrue($container->has('Nkamuo\NotificationTrackerBundle\Command\ProcessFailedMessagesCommand'));
    }
}
