<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Tests\Functional;

use Nkamuo\NotificationTrackerBundle\Entity\EmailMessage;
use Nkamuo\NotificationTrackerBundle\Entity\Message;
use PHPUnit\Framework\TestCase;

class BasicEntityTest extends TestCase
{
    public function testEmailMessageCreation(): void
    {
        $emailMessage = new EmailMessage();
        $emailMessage->setSubject('Test Subject');
        $emailMessage->setFromEmail('sender@example.com');
        $emailMessage->setFromName('Sender Name');
        $emailMessage->setTransportName('test_transport');

        $this->assertEquals('Test Subject', $emailMessage->getSubject());
        $this->assertEquals('sender@example.com', $emailMessage->getFromEmail());
        $this->assertEquals('Sender Name', $emailMessage->getFromName());
        $this->assertEquals('test_transport', $emailMessage->getTransportName());
        $this->assertEquals(Message::STATUS_PENDING, $emailMessage->getStatus());
    }

    public function testEmailMessageStatusChanges(): void
    {
        $emailMessage = new EmailMessage();
        
        // Test initial state
        $this->assertEquals(Message::STATUS_PENDING, $emailMessage->getStatus());
        
        // Test status updates
        $emailMessage->setStatus(Message::STATUS_SENT);
        $this->assertEquals(Message::STATUS_SENT, $emailMessage->getStatus());
        
        $emailMessage->setStatus(Message::STATUS_DELIVERED);
        $this->assertEquals(Message::STATUS_DELIVERED, $emailMessage->getStatus());
        
        $emailMessage->setStatus(Message::STATUS_FAILED);
        $this->assertEquals(Message::STATUS_FAILED, $emailMessage->getStatus());
    }

    public function testEmailMessageAddRecipient(): void
    {
        $emailMessage = new EmailMessage();
        
        // Create a proper MessageRecipient entity
        $recipient = new \Nkamuo\NotificationTrackerBundle\Entity\MessageRecipient();
        $recipient->setAddress('test@example.com');
        $recipient->setName('Test User');
        $recipient->setType('to');
        
        // Test adding recipients
        $emailMessage->addRecipient($recipient);
        
        $recipients = $emailMessage->getRecipients();
        $this->assertCount(1, $recipients);
        
        $recipientFromCollection = $recipients->first();
        $this->assertEquals('test@example.com', $recipientFromCollection->getAddress());
        $this->assertEquals('Test User', $recipientFromCollection->getName());
        $this->assertEquals('to', $recipientFromCollection->getType());
    }
}
