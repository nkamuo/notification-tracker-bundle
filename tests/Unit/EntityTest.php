<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Nkamuo\NotificationTrackerBundle\Entity\EmailMessage;
use Nkamuo\NotificationTrackerBundle\Entity\Message;
use Symfony\Component\Mime\Address;
use Symfony\Component\Uid\Ulid;

class EntityTest extends TestCase
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

    public function testMessageIdGeneration(): void
    {
        $message = new EmailMessage();
        
        // ID should be auto-generated
        $this->assertInstanceOf(Ulid::class, $message->getId());
        $this->assertNotNull($message->getId());
    }

    public function testMessageStatusUpdate(): void
    {
        $message = new EmailMessage();
        
        $this->assertEquals(Message::STATUS_PENDING, $message->getStatus());
        
        $message->setStatus(Message::STATUS_SENT);
        $this->assertEquals(Message::STATUS_SENT, $message->getStatus());
        
        $message->setStatus(Message::STATUS_DELIVERED);
        $this->assertEquals(Message::STATUS_DELIVERED, $message->getStatus());
    }
}
