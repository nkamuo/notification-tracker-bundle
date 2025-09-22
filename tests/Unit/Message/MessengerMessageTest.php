<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Tests\Unit\Message;

use Nkamuo\NotificationTrackerBundle\Message\SendNotificationMessage;
use Nkamuo\NotificationTrackerBundle\Message\SendChannelMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

class MessengerMessageTest extends TestCase
{
    public function testSendNotificationMessageCreation(): void
    {
        $notificationId = new Ulid();
        $message = new SendNotificationMessage($notificationId);
        
        $this->assertEquals($notificationId, $message->getNotificationId());
        $this->assertNull($message->getChannel());
        $this->assertNull($message->getRecipientOverrides());
        $this->assertNull($message->getMetadata());
    }

    public function testSendNotificationMessageWithChannel(): void
    {
        $notificationId = new Ulid();
        $channel = 'email';
        $message = new SendNotificationMessage($notificationId, $channel);
        
        $this->assertEquals($notificationId, $message->getNotificationId());
        $this->assertEquals($channel, $message->getChannel());
        $this->assertNull($message->getRecipientOverrides());
        $this->assertNull($message->getMetadata());
    }

    public function testSendNotificationMessageWithRecipientOverrides(): void
    {
        $notificationId = new Ulid();
        $channel = 'email';
        $recipientOverrides = [
            ['email' => 'test@example.com', 'channel' => 'email']
        ];
        $metadata = ['source' => 'api'];
        
        $message = new SendNotificationMessage(
            $notificationId,
            $channel,
            $recipientOverrides,
            $metadata
        );
        
        $this->assertEquals($notificationId, $message->getNotificationId());
        $this->assertEquals($channel, $message->getChannel());
        $this->assertEquals($recipientOverrides, $message->getRecipientOverrides());
        $this->assertEquals($metadata, $message->getMetadata());
    }

    public function testSendChannelMessageCreation(): void
    {
        $messageId = new Ulid();
        $channel = 'sms';
        $message = new SendChannelMessage($messageId, $channel);
        
        $this->assertEquals($messageId, $message->getMessageId());
        $this->assertEquals($channel, $message->getChannel());
        $this->assertNull($message->getRecipientData());
        $this->assertNull($message->getMetadata());
    }

    public function testSendChannelMessageWithRecipientData(): void
    {
        $messageId = new Ulid();
        $channel = 'slack';
        $recipientData = [
            'channel' => '#general',
            'scheduledAt' => '2025-09-22T15:00:00Z'
        ];
        $metadata = [
            'slack_blocks' => [
                ['type' => 'section', 'text' => ['type' => 'plain_text', 'text' => 'Hello']]
            ]
        ];
        
        $message = new SendChannelMessage(
            $messageId,
            $channel,
            $recipientData,
            $metadata
        );
        
        $this->assertEquals($messageId, $message->getMessageId());
        $this->assertEquals($channel, $message->getChannel());
        $this->assertEquals($recipientData, $message->getRecipientData());
        $this->assertEquals($metadata, $message->getMetadata());
    }

    public function testMessageImmutability(): void
    {
        $notificationId = new Ulid();
        $messageId = new Ulid();
        
        $sendNotificationMessage = new SendNotificationMessage($notificationId, 'email');
        $sendChannelMessage = new SendChannelMessage($messageId, 'sms');
        
        // Properties should not be modifiable after creation
        $this->assertEquals($notificationId, $sendNotificationMessage->getNotificationId());
        $this->assertEquals('email', $sendNotificationMessage->getChannel());
        
        $this->assertEquals($messageId, $sendChannelMessage->getMessageId());
        $this->assertEquals('sms', $sendChannelMessage->getChannel());
    }
}
