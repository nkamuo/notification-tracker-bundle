<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Tests\Unit;

use Nkamuo\NotificationTrackerBundle\Enum\NotificationDirection;
use Nkamuo\NotificationTrackerBundle\Enum\NotificationStatus;
use PHPUnit\Framework\TestCase;

class NotificationDirectionRefactoringTest extends TestCase
{
    public function testDirectionEnumOnlyHasInboundAndOutbound(): void
    {
        // Test that only INBOUND and OUTBOUND cases exist
        $cases = NotificationDirection::cases();
        $this->assertCount(2, $cases);
        
        $caseNames = array_map(fn($case) => $case->name, $cases);
        $this->assertContains('INBOUND', $caseNames);
        $this->assertContains('OUTBOUND', $caseNames);
        $this->assertNotContains('DRAFT', $caseNames);
    }

    public function testDirectionValues(): void
    {
        $this->assertEquals('inbound', NotificationDirection::INBOUND->value);
        $this->assertEquals('outbound', NotificationDirection::OUTBOUND->value);
    }

    public function testDirectionFromValue(): void
    {
        $this->assertEquals(NotificationDirection::INBOUND, NotificationDirection::from('inbound'));
        $this->assertEquals(NotificationDirection::OUTBOUND, NotificationDirection::from('outbound'));
    }

    public function testDraftValueThrowsException(): void
    {
        $this->expectException(\ValueError::class);
        NotificationDirection::from('draft');
    }

    public function testDirectionIsIncomingMethod(): void
    {
        $this->assertTrue(NotificationDirection::INBOUND->isIncoming());
        $this->assertFalse(NotificationDirection::OUTBOUND->isIncoming());
    }

    public function testDirectionIsOutgoingMethod(): void
    {
        $this->assertTrue(NotificationDirection::OUTBOUND->isOutgoing());
        $this->assertFalse(NotificationDirection::INBOUND->isOutgoing());
    }

    public function testDeprecatedIsDraftMethod(): void
    {
        // isDraft() should always return false since there's no DRAFT case
        $this->assertFalse(NotificationDirection::INBOUND->isDraft());
        $this->assertFalse(NotificationDirection::OUTBOUND->isDraft());
    }

    public function testNotificationStatusHasDraft(): void
    {
        // Verify that draft functionality moved to NotificationStatus
        $cases = NotificationStatus::cases();
        $caseNames = array_map(fn($case) => $case->name, $cases);
        $this->assertContains('DRAFT', $caseNames);
    }

    public function testNotificationStatusDraftValue(): void
    {
        $this->assertEquals('draft', NotificationStatus::DRAFT->value);
    }

    public function testStatusIsDraftMethod(): void
    {
        $this->assertTrue(NotificationStatus::DRAFT->isDraft());
        $this->assertFalse(NotificationStatus::SENT->isDraft());
        $this->assertFalse(NotificationStatus::QUEUED->isDraft());
    }
}
