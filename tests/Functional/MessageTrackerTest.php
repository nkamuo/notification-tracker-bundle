<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Tests\Functional;

use Nkamuo\NotificationTrackerBundle\Entity\EmailMessage;
use Nkamuo\NotificationTrackerBundle\Service\MessageTracker;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class MessageTrackerTest extends KernelTestCase
{
    private MessageTracker $messageTracker;

    protected function setUp(): void
    {
        self::bootKernel(['environment' => 'test']);
        
        // Get the service from the container
        $this->messageTracker = static::getContainer()->get(MessageTracker::class);
        
        // Set up the database schema
        $this->setUpDatabase();
    }

    private function setUpDatabase(): void
    {
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        
        // Drop and create schema
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testTrackEmail(): void
    {
        $email = (new Email())
            ->from(new Address('sender@example.com', 'Sender Name'))
            ->to(new Address('recipient@example.com', 'Recipient Name'))
            ->subject('Test Email Subject')
            ->text('This is a test email body');

        $emailMessage = $this->messageTracker->trackEmail($email, 'test_transport');

        $this->assertInstanceOf(EmailMessage::class, $emailMessage);
        $this->assertEquals('Test Email Subject', $emailMessage->getSubject());
        $this->assertEquals('sender@example.com', $emailMessage->getFromEmail());
        $this->assertEquals('Sender Name', $emailMessage->getFromName());
        $this->assertEquals('test_transport', $emailMessage->getTransportName());
        $this->assertNotNull($emailMessage->getId());
        
        // Check that message was persisted
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $savedMessage = $entityManager->find(EmailMessage::class, $emailMessage->getId());
        $this->assertNotNull($savedMessage);
        $this->assertEquals('Test Email Subject', $savedMessage->getSubject());
    }

    public function testTrackEmailWithMultipleRecipients(): void
    {
        $email = (new Email())
            ->from(new Address('sender@example.com'))
            ->to(
                new Address('recipient1@example.com', 'Recipient 1'),
                new Address('recipient2@example.com', 'Recipient 2')
            )
            ->cc(new Address('cc@example.com', 'CC Recipient'))
            ->bcc(new Address('bcc@example.com', 'BCC Recipient'))
            ->subject('Multi-recipient Test')
            ->text('Test with multiple recipients');

        $emailMessage = $this->messageTracker->trackEmail($email);

        $this->assertCount(4, $emailMessage->getRecipients());
        
        $recipients = $emailMessage->getRecipients()->toArray();
        $this->assertEquals('recipient1@example.com', $recipients[0]->getEmail());
        $this->assertEquals('Recipient 1', $recipients[0]->getName());
        $this->assertEquals('to', $recipients[0]->getType());
        
        $this->assertEquals('cc@example.com', $recipients[2]->getEmail());
        $this->assertEquals('cc', $recipients[2]->getType());
    }

    public function testAddEventToMessage(): void
    {
        $email = (new Email())
            ->from('sender@example.com')
            ->to('recipient@example.com')
            ->subject('Event Test')
            ->text('Test message for events');

        $emailMessage = $this->messageTracker->trackEmail($email);
        
        $event = $this->messageTracker->addEvent(
            $emailMessage,
            'delivered',
            ['delivery_time' => time(), 'server' => 'test-server']
        );

        $this->assertEquals('delivered', $event->getEventType());
        $this->assertEquals(['delivery_time' => time(), 'server' => 'test-server'], $event->getEventData());
        $this->assertEquals($emailMessage, $event->getMessage());
    }

    public function testFindMessageById(): void
    {
        $email = (new Email())
            ->from('sender@example.com')
            ->to('recipient@example.com')
            ->subject('Find Test')
            ->text('Test finding message');

        $emailMessage = $this->messageTracker->trackEmail($email);
        $messageId = $emailMessage->getId()->toRfc4122();

        $foundMessage = $this->messageTracker->findById($messageId);
        
        $this->assertNotNull($foundMessage);
        $this->assertInstanceOf(EmailMessage::class, $foundMessage);
        $this->assertEquals($emailMessage->getId(), $foundMessage->getId());
        
        /** @var EmailMessage $foundEmailMessage */
        $foundEmailMessage = $foundMessage;
        $this->assertEquals('Find Test', $foundEmailMessage->getSubject());
    }
}
