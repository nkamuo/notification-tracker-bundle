<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Tests\Functional;

use Nkamuo\NotificationTrackerBundle\Entity\EmailMessage;
use Nkamuo\NotificationTrackerBundle\Message\TrackEmailMessage;
use Nkamuo\NotificationTrackerBundle\MessageHandler\TrackEmailMessageHandler;
use Nkamuo\NotificationTrackerBundle\Service\MessageTracker;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Uid\Ulid;

class MessageHandlerTest extends KernelTestCase
{
    private MessageTracker $messageTracker;
    private TrackEmailMessageHandler $trackEmailMessageHandler;

    protected function setUp(): void
    {
        self::bootKernel(['environment' => 'test']);
        
        $this->messageTracker = static::getContainer()->get(MessageTracker::class);
        $this->trackEmailMessageHandler = static::getContainer()->get(TrackEmailMessageHandler::class);
        
        $this->setUpDatabase();
    }

    private function setUpDatabase(): void
    {
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testTrackEmailMessageHandler(): void
    {
        // First create an email message to track events for
        $email = (new Email())
            ->from(new Address('sender@example.com'))
            ->to(new Address('recipient@example.com'))
            ->subject('Handler Test Email')
            ->text('Test email for handler');

        $emailMessage = $this->messageTracker->trackEmail($email);
        
        // Create a tracking event message
        $trackEmailMessage = new TrackEmailMessage(
            $emailMessage->getId(),
            'opened',
            [
                'timestamp' => time(),
                'ip_address' => '192.168.1.1',
                'user_agent' => 'Test User Agent'
            ]
        );

        // Process the message through the handler
        ($this->trackEmailMessageHandler)($trackEmailMessage);

        // Verify the event was created
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $entityManager->refresh($emailMessage);

        $events = $emailMessage->getEvents();
        $this->assertCount(1, $events);
        
        $event = $events->first();
        $this->assertEquals('opened', $event->getEventType());
        $this->assertEquals('192.168.1.1', $event->getIpAddress());
        $this->assertEquals('Test User Agent', $event->getUserAgent());
    }

    public function testTrackEmailMessageHandlerWithNonExistentMessage(): void
    {
        // Create a message with a non-existent message ID
        $nonExistentId = new Ulid();
        $trackEmailMessage = new TrackEmailMessage(
            $nonExistentId,
            'delivered',
            []
        );

        // The handler should handle this gracefully without throwing an exception
        ($this->trackEmailMessageHandler)($trackEmailMessage);
        
        // Since no exception was thrown, the test passes
        $this->assertTrue(true);
    }
}
