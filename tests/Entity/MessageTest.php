<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Tests\Entity;

use Nkamuo\NotificationTrackerBundle\Entity\Message;
use Nkamuo\NotificationTrackerBundle\Entity\EmailMessage;
use PHPUnit\Framework\TestCase;

class MessageTest extends TestCase
{
    public function testMessengerStampIdGetterSetter(): void
    {
        $message = new EmailMessage();
        
        // Test initial value
        $this->assertNull($message->getMessengerStampId());
        
        // Test setting value
        $stampId = '01HKQM7Y8N2XC4T6B9F3E8Z5V1';
        $result = $message->setMessengerStampId($stampId);
        
        // Test fluent interface
        $this->assertSame($message, $result);
        
        // Test getting value
        $this->assertEquals($stampId, $message->getMessengerStampId());
    }

    public function testMessengerStampIdCanBeNull(): void
    {
        $message = new EmailMessage();
        
        $message->setMessengerStampId('test-id');
        $this->assertEquals('test-id', $message->getMessengerStampId());
        
        // Test setting back to null
        $message->setMessengerStampId(null);
        $this->assertNull($message->getMessengerStampId());
    }

    public function testContentFingerprintGetterSetter(): void
    {
        $message = new EmailMessage();
        
        // Test initial value
        $this->assertNull($message->getContentFingerprint());
        
        // Test setting value
        $fingerprint = 'sha256:abcd1234567890efgh';
        $result = $message->setContentFingerprint($fingerprint);
        
        // Test fluent interface
        $this->assertSame($message, $result);
        
        // Test getting value
        $this->assertEquals($fingerprint, $message->getContentFingerprint());
    }

    public function testContentFingerprintCanBeNull(): void
    {
        $message = new EmailMessage();
        
        $message->setContentFingerprint('test-fingerprint');
        $this->assertEquals('test-fingerprint', $message->getContentFingerprint());
        
        // Test setting back to null
        $message->setContentFingerprint(null);
        $this->assertNull($message->getContentFingerprint());
    }

    public function testBothFieldsWorkTogether(): void
    {
        $message = new EmailMessage();
        
        $stampId = '01HKQM7Y8N2XC4T6B9F3E8Z5V1';
        $fingerprint = 'sha256:1234567890abcdef';
        
        // Set both values
        $message->setMessengerStampId($stampId);
        $message->setContentFingerprint($fingerprint);
        
        // Verify both are stored correctly
        $this->assertEquals($stampId, $message->getMessengerStampId());
        $this->assertEquals($fingerprint, $message->getContentFingerprint());
        
        // Test that setting one doesn't affect the other
        $message->setMessengerStampId('new-stamp-id');
        $this->assertEquals('new-stamp-id', $message->getMessengerStampId());
        $this->assertEquals($fingerprint, $message->getContentFingerprint());
    }

    public function testMessageWithMetadataAndNewFields(): void
    {
        $message = new EmailMessage();
        
        // Set traditional fields
        $message->setSubject('Test Subject');
        $message->setFromEmail('test@example.com');
        $message->setMetadata(['key' => 'value']);
        
        // Set new fields
        $message->setMessengerStampId('stamp-123');
        $message->setContentFingerprint('fingerprint-456');
        
        // Verify all fields coexist
        $this->assertEquals('Test Subject', $message->getSubject());
        $this->assertEquals('test@example.com', $message->getFromEmail());
        $this->assertEquals(['key' => 'value'], $message->getMetadata());
        $this->assertEquals('stamp-123', $message->getMessengerStampId());
        $this->assertEquals('fingerprint-456', $message->getContentFingerprint());
    }

    public function testEmptyStringValues(): void
    {
        $message = new EmailMessage();
        
        // Test empty strings
        $message->setMessengerStampId('');
        $message->setContentFingerprint('');
        
        $this->assertEquals('', $message->getMessengerStampId());
        $this->assertEquals('', $message->getContentFingerprint());
    }
}
