<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Tests;

use Nkamuo\NotificationTrackerBundle\Messenger\Middleware\NotificationTrackingMiddleware;
use Nkamuo\NotificationTrackerBundle\Stamp\NotificationTrackingStamp;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Mime\Email;

/**
 * Functional test to verify stamp-based retry tracking works end-to-end
 */
class StampBasedRetryTrackingFunctionalTest extends TestCase
{
    public function testStampBasedRetryTrackingFlow(): void
    {
        // Arrange: Create email and middleware
        $email = new Email();
        $email->from('sender@example.com')
            ->to('recipient@example.com')
            ->subject('Test Retry Tracking')
            ->text('Testing stamp-based retry tracking');

        $sendEmailMessage = new SendEmailMessage($email);
        $envelope = new Envelope($sendEmailMessage);
        $middleware = new NotificationTrackingMiddleware();

        // Act: Process envelope through middleware (first attempt)
        $result1 = $middleware->handle($envelope, $this->createPassthroughStack());

        // Assert: Verify stamp was added
        $stamp1 = $result1->last(NotificationTrackingStamp::class);
        $this->assertNotNull($stamp1);
        $this->assertInstanceOf(NotificationTrackingStamp::class, $stamp1);
        
        // Verify stamp ID format (ULID)
        $stampId = $stamp1->getId();
        $this->assertEquals(26, strlen($stampId));
        $this->assertMatchesRegularExpression('/^[0-9A-Z]{26}$/', $stampId);

        // Verify stamp ID was added to email headers
        $processedMessage = $result1->getMessage();
        $processedEmail = $processedMessage->getMessage();
        $this->assertTrue($processedEmail->getHeaders()->has('X-Stamp-ID'));
        $this->assertEquals($stampId, $processedEmail->getHeaders()->get('X-Stamp-ID')->getBodyAsString());

        // Act: Process same envelope again (retry scenario)
        $result2 = $middleware->handle($result1, $this->createPassthroughStack());

        // Assert: Verify same stamp is preserved
        $stamp2 = $result2->last(NotificationTrackingStamp::class);
        $this->assertNotNull($stamp2);
        $this->assertEquals($stampId, $stamp2->getId());
        
        // Verify header is still present and unchanged
        $retryMessage = $result2->getMessage();
        $retryEmail = $retryMessage->getMessage();
        $this->assertEquals($stampId, $retryEmail->getHeaders()->get('X-Stamp-ID')->getBodyAsString());
    }

    public function testUniqueStampsForDifferentMessages(): void
    {
        $middleware = new NotificationTrackingMiddleware();
        $stamps = [];

        // Create multiple different emails
        for ($i = 0; $i < 5; $i++) {
            $email = new Email();
            $email->from('sender@example.com')
                ->to("recipient{$i}@example.com")
                ->subject("Test Email {$i}");

            $envelope = new Envelope(new SendEmailMessage($email));
            $result = $middleware->handle($envelope, $this->createPassthroughStack());
            
            $stamp = $result->last(NotificationTrackingStamp::class);
            $this->assertNotNull($stamp);
            $stamps[] = $stamp->getId();
        }

        // Verify all stamps are unique
        $uniqueStamps = array_unique($stamps);
        $this->assertCount(5, $uniqueStamps);
        $this->assertEquals($stamps, $uniqueStamps);
    }

    public function testNonEmailMessagesIgnored(): void
    {
        $middleware = new NotificationTrackingMiddleware();
        
        $nonEmailMessage = new \stdClass();
        $envelope = new Envelope($nonEmailMessage);
        
        $result = $middleware->handle($envelope, $this->createPassthroughStack());
        
        // Should not add stamp to non-email messages
        $stamp = $result->last(NotificationTrackingStamp::class);
        $this->assertNull($stamp);
    }

    public function testHeaderNotDuplicatedWhenExists(): void
    {
        $middleware = new NotificationTrackingMiddleware();
        
        $email = new Email();
        $email->from('test@example.com')
            ->to('test@example.com')
            ->subject('Test');
        
        // Pre-add the header
        $existingStampId = 'pre-existing-stamp-id';
        $email->getHeaders()->addTextHeader('X-Stamp-ID', $existingStampId);

        $envelope = new Envelope(new SendEmailMessage($email));
        $result = $middleware->handle($envelope, $this->createPassthroughStack());

        $processedMessage = $result->getMessage();
        $processedEmail = $processedMessage->getMessage();
        
        // Should not duplicate the header
        $headers = $processedEmail->getHeaders()->all('X-Stamp-ID');
        $this->assertCount(1, $headers);
        $this->assertEquals($existingStampId, $headers[0]->getBodyAsString());
    }

    /**
     * Create a passthrough stack for testing middleware
     */
    private function createPassthroughStack(): StackInterface
    {
        return new class implements StackInterface {
            public function next(): \Symfony\Component\Messenger\Middleware\MiddlewareInterface
            {
                return new class implements \Symfony\Component\Messenger\Middleware\MiddlewareInterface {
                    public function handle(Envelope $envelope, StackInterface $stack): Envelope
                    {
                        return $envelope;
                    }
                };
            }
            
            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                return $envelope;
            }
        };
    }
}
