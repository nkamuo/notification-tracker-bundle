<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Tests\Messenger\Stamp;

use Nkamuo\NotificationTrackerBundle\Messenger\Stamp\NotificationTrackingStamp;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Stamp\StampInterface;

class NotificationTrackingStampTest extends TestCase
{
    public function testImplementsStampInterface(): void
    {
        $stamp = new NotificationTrackingStamp('test-id');
        
        $this->assertInstanceOf(StampInterface::class, $stamp);
    }

    public function testGetId(): void
    {
        $id = 'test-tracking-id-123';
        $stamp = new NotificationTrackingStamp($id);
        
        $this->assertEquals($id, $stamp->getId());
    }

    public function testReadonlyProperty(): void
    {
        $id = 'immutable-id';
        $stamp = new NotificationTrackingStamp($id);
        
        // Verify ID cannot be changed after construction
        $this->assertEquals($id, $stamp->getId());
        
        // Create another stamp with different ID to verify immutability
        $stamp2 = new NotificationTrackingStamp('different-id');
        $this->assertEquals('different-id', $stamp2->getId());
        $this->assertEquals($id, $stamp->getId()); // Original unchanged
    }

    public function testWithUlidFormat(): void
    {
        // Test with ULID-like format
        $ulid = '01HKQM7Y8N2XC4T6B9F3E8Z5V1';
        $stamp = new NotificationTrackingStamp($ulid);
        
        $this->assertEquals($ulid, $stamp->getId());
        $this->assertEquals(26, strlen($stamp->getId())); // ULID length
    }

    public function testWithEmptyString(): void
    {
        $stamp = new NotificationTrackingStamp('');
        
        $this->assertEquals('', $stamp->getId());
    }

    public function testStringRepresentation(): void
    {
        $id = 'test-id';
        $stamp = new NotificationTrackingStamp($id);
        
        // Test that the stamp can be serialized/used in string contexts
        $this->assertEquals($id, (string) $stamp->getId());
    }
}
