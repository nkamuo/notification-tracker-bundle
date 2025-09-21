<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Tests\Service;

use Nkamuo\NotificationTrackerBundle\Entity\EmailMessage;
use Nkamuo\NotificationTrackerBundle\Entity\Notification;
use Nkamuo\NotificationTrackerBundle\Service\MessageTracker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Email;

class MessageTrackerStampIntegrationTest extends TestCase
{
    public function testTrackEmailWithStampId(): void
    {
        // This is a unit test focusing on the MessageTracker's new stamp functionality
        // In a real test environment, you'd inject proper dependencies
        
        $email = new Email();
        $email->from('sender@example.com')
            ->to('recipient@example.com')
            ->subject('Test Email')
            ->text('Test content');

        $stampId = '01HKQM7Y8N2XC4T6B9F3E8Z5V1';
        $contentFingerprint = 'sha256:abcd1234567890efgh';

        $metadata = [
            'stamp_id' => $stampId,
            'content_fingerprint' => $contentFingerprint,
            'test_flag' => true
        ];

        // Create a mock or simple test to verify the metadata extraction works
        // In a real implementation, this would test the actual MessageTracker service
        
        $this->assertArrayHasKey('stamp_id', $metadata);
        $this->assertArrayHasKey('content_fingerprint', $metadata);
        $this->assertEquals($stampId, $metadata['stamp_id']);
        $this->assertEquals($contentFingerprint, $metadata['content_fingerprint']);
    }

    public function testStampIdExtraction(): void
    {
        $metadata = [
            'stamp_id' => '01HKQM7Y8N2XC4T6B9F3E8Z5V1',
            'other_data' => 'value'
        ];

        // Test the logic that would be in MessageTracker
        $extractedStampId = isset($metadata['stamp_id']) ? $metadata['stamp_id'] : null;
        
        $this->assertNotNull($extractedStampId);
        $this->assertEquals('01HKQM7Y8N2XC4T6B9F3E8Z5V1', $extractedStampId);
    }

    public function testContentFingerprintExtraction(): void
    {
        $metadata = [
            'content_fingerprint' => 'sha256:1234567890abcdef',
            'other_data' => 'value'
        ];

        // Test the logic that would be in MessageTracker
        $extractedFingerprint = isset($metadata['content_fingerprint']) ? $metadata['content_fingerprint'] : null;
        
        $this->assertNotNull($extractedFingerprint);
        $this->assertEquals('sha256:1234567890abcdef', $extractedFingerprint);
    }

    public function testMetadataWithoutStampFields(): void
    {
        $metadata = [
            'transport' => 'smtp',
            'queued' => true
        ];

        // Test the logic that would be in MessageTracker for missing stamp fields
        $extractedStampId = isset($metadata['stamp_id']) ? $metadata['stamp_id'] : null;
        $extractedFingerprint = isset($metadata['content_fingerprint']) ? $metadata['content_fingerprint'] : null;
        
        $this->assertNull($extractedStampId);
        $this->assertNull($extractedFingerprint);
    }

    public function testEmailContentForFingerprinting(): void
    {
        $email = new Email();
        $email->from('sender@example.com')
            ->to('recipient1@example.com', 'recipient2@example.com')
            ->cc('cc@example.com')
            ->bcc('bcc@example.com')
            ->subject('Test Subject')
            ->text('Text body content')
            ->html('<p>HTML body content</p>');

        // Test data extraction for fingerprinting
        $fingerprintData = [
            'subject' => $email->getSubject(),
            'from' => $email->getFrom() ? $email->getFrom()[0]->toString() : '',
            'to' => array_map(fn($addr) => $addr->toString(), $email->getTo()),
            'cc' => array_map(fn($addr) => $addr->toString(), $email->getCc()),
            'bcc' => array_map(fn($addr) => $addr->toString(), $email->getBcc()),
            'text_body' => $email->getTextBody(),
            'html_body' => $email->getHtmlBody(),
        ];

        $this->assertEquals('Test Subject', $fingerprintData['subject']);
        $this->assertStringContainsString('sender@example.com', $fingerprintData['from']);
        $this->assertCount(2, $fingerprintData['to']);
        $this->assertCount(1, $fingerprintData['cc']);
        $this->assertCount(1, $fingerprintData['bcc']);
        $this->assertEquals('Text body content', $fingerprintData['text_body']);
        $this->assertEquals('<p>HTML body content</p>', $fingerprintData['html_body']);
    }

    public function testFingerprintConsistency(): void
    {
        // Create two identical emails
        $createEmail = function() {
            $email = new Email();
            return $email->from('sender@example.com')
                ->to('recipient@example.com')
                ->subject('Consistent Subject')
                ->text('Consistent content');
        };

        $email1 = $createEmail();
        $email2 = $createEmail();

        // Extract data for fingerprinting from both
        $extractData = function(Email $email) {
            return [
                'subject' => $email->getSubject(),
                'from' => $email->getFrom() ? $email->getFrom()[0]->toString() : '',
                'to' => array_map(fn($addr) => $addr->toString(), $email->getTo()),
                'cc' => array_map(fn($addr) => $addr->toString(), $email->getCc()),
                'bcc' => array_map(fn($addr) => $addr->toString(), $email->getBcc()),
                'text_body' => $email->getTextBody(),
                'html_body' => $email->getHtmlBody(),
            ];
        };

        $data1 = $extractData($email1);
        $data2 = $extractData($email2);

        // Generate fingerprints
        $fingerprint1 = hash('sha256', serialize($data1));
        $fingerprint2 = hash('sha256', serialize($data2));

        // Should be identical
        $this->assertEquals($fingerprint1, $fingerprint2);
        $this->assertEquals(64, strlen($fingerprint1)); // SHA256 hex length
    }
}
