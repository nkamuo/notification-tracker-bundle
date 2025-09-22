<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Tests\Integration;

use Nkamuo\NotificationTrackerBundle\Messenger\Middleware\NotificationTrackingMiddleware;
use Nkamuo\NotificationTrackerBundle\Stamp\NotificationTrackingStamp;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Mime\Email;

/**
 * Integration test to verify the complete stamp-based retry tracking flow
 */
class StampBasedRetryTrackingTest extends TestCase
{
    public function testCompleteRetryTrackingFlow(): void
    {
        // Step 1: Create an email message
        $email = new Email();
        $email->from('sender@example.com')
            ->to('recipient@example.com')
            ->subject('Test Email for Retry Tracking')
            ->text('This email tests the retry tracking mechanism');

        $sendEmailMessage = new SendEmailMessage($email);
        $envelope = new Envelope($sendEmailMessage);

        // Step 2: Process through middleware (first attempt)
        $middleware = new NotificationTrackingMiddleware();
        $processedEnvelope = $this->processEnvelopeThroughMiddleware($envelope, $middleware);

        // Verify stamp was added
        $stamp = $processedEnvelope->last(NotificationTrackingStamp::class);
        $this->assertNotNull($stamp, 'Stamp should be added to new messages');
        $this->assertInstanceOf(NotificationTrackingStamp::class, $stamp);

        // Verify stamp ID was added to email headers
        $processedMessage = $processedEnvelope->getMessage();
        $processedEmail = $processedMessage->getMessage();
        $this->assertTrue($processedEmail->getHeaders()->has('X-Stamp-ID'));
        $this->assertEquals($stamp->getId(), $processedEmail->getHeaders()->get('X-Stamp-ID')->getBodyAsString());

        // Step 3: Simulate retry (same envelope, same stamp)
        $retryEnvelope = $processedEnvelope; // In real scenario, this would be deserialized
        $retryProcessedEnvelope = $this->processEnvelopeThroughMiddleware($retryEnvelope, $middleware);

        // Verify same stamp is preserved
        $retryStamp = $retryProcessedEnvelope->last(NotificationTrackingStamp::class);
        $this->assertNotNull($retryStamp);
        $this->assertEquals($stamp->getId(), $retryStamp->getId(), 'Stamp ID should be preserved across retries');

        // Step 4: Verify stamp ID consistency
        $retryMessage = $retryProcessedEnvelope->getMessage();
        $retryEmail = $retryMessage->getMessage();
        $this->assertEquals($stamp->getId(), $retryEmail->getHeaders()->get('X-Stamp-ID')->getBodyAsString());
    }

    public function testDifferentMessagesGetDifferentStamps(): void
    {
        $middleware = new NotificationTrackingMiddleware();

        // Create first email
        $email1 = new Email();
        $email1->from('sender@example.com')
            ->to('recipient1@example.com')
            ->subject('First Email');
        $envelope1 = new Envelope(new SendEmailMessage($email1));

        // Create second email
        $email2 = new Email();
        $email2->from('sender@example.com')
            ->to('recipient2@example.com')
            ->subject('Second Email');
        $envelope2 = new Envelope(new SendEmailMessage($email2));

        // Process both through middleware
        $processed1 = $this->processEnvelopeThroughMiddleware($envelope1, $middleware);
        $processed2 = $this->processEnvelopeThroughMiddleware($envelope2, $middleware);

        // Verify different stamp IDs
        $stamp1 = $processed1->last(NotificationTrackingStamp::class);
        $stamp2 = $processed2->last(NotificationTrackingStamp::class);

        $this->assertNotNull($stamp1);
        $this->assertNotNull($stamp2);
        $this->assertNotEquals($stamp1->getId(), $stamp2->getId(), 'Different messages should get different stamp IDs');
    }

    public function testStampIdFormat(): void
    {
        $middleware = new NotificationTrackingMiddleware();
        
        $email = new Email();
        $email->from('test@example.com')->to('test@example.com')->subject('Test');
        $envelope = new Envelope(new SendEmailMessage($email));

        $processed = $this->processEnvelopeThroughMiddleware($envelope, $middleware);
        $stamp = $processed->last(NotificationTrackingStamp::class);

        $this->assertNotNull($stamp);
        $stampId = $stamp->getId();

        // Verify ULID format (26 characters, alphanumeric uppercase)
        $this->assertEquals(26, strlen($stampId), 'Stamp ID should be 26 characters (ULID format)');
        $this->assertMatchesRegularExpression('/^[0-9A-Z]{26}$/', $stampId, 'Stamp ID should match ULID pattern');
    }

    public function testHeaderInjectionPrevention(): void
    {
        $middleware = new NotificationTrackingMiddleware();
        
        // Create email with existing X-Stamp-ID header
        $email = new Email();
        $email->from('test@example.com')
            ->to('test@example.com')
            ->subject('Test');
        $email->getHeaders()->addTextHeader('X-Stamp-ID', 'existing-header-value');

        $envelope = new Envelope(new SendEmailMessage($email));
        $processed = $this->processEnvelopeThroughMiddleware($envelope, $middleware);

        $processedMessage = $processed->getMessage();
        $processedEmail = $processedMessage->getMessage();

        // Should not duplicate the header
        $headers = $processedEmail->getHeaders()->all('X-Stamp-ID');
        $this->assertCount(1, $headers, 'Should not duplicate X-Stamp-ID header');
        $this->assertEquals('existing-header-value', $headers[0]->getBodyAsString());
    }

    public function testNonEmailMessagesAreIgnored(): void
    {
        $middleware = new NotificationTrackingMiddleware();
        
        // Create non-email message
        $nonEmailMessage = new class {
            public function getData(): string
            {
                return 'not an email';
            }
        };
        $envelope = new Envelope($nonEmailMessage);

        $processed = $this->processEnvelopeThroughMiddleware($envelope, $middleware);

        // Should not add stamp to non-email messages
        $stamp = $processed->last(NotificationTrackingStamp::class);
        $this->assertNull($stamp, 'Non-email messages should not get stamps');
    }

    /**
     * Helper method to simulate middleware processing
     */
    private function processEnvelopeThroughMiddleware(Envelope $envelope, NotificationTrackingMiddleware $middleware): Envelope
    {
        // Create a simple stack that just returns the envelope
        $stack = new class implements \Symfony\Component\Messenger\Middleware\StackInterface {
            private NotificationTrackingMiddleware $middleware;
            
            public function __construct(NotificationTrackingMiddleware $middleware)
            {
                $this->middleware = $middleware;
            }
            
            public function next(): \Symfony\Component\Messenger\Middleware\MiddlewareInterface
            {
                return $this->middleware;
            }
            
            public function handle(Envelope $envelope, \Symfony\Component\Messenger\Middleware\StackInterface $stack): Envelope
            {
                return $envelope; // Just return the envelope as-is
            }
        };

        return $middleware->handle($envelope, $stack);
    }
}
