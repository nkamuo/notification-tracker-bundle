<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Tests\Messenger;

use Nkamuo\NotificationTrackerBundle\Messenger\Middleware\NotificationTrackingMiddleware;
use Nkamuo\NotificationTrackerBundle\Messenger\Stamp\NotificationTrackingStamp;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\DesktopMessage;
use Symfony\Component\Notifier\Message\EmailMessage;
use Symfony\Component\Notifier\Message\PushMessage;
use Symfony\Component\Notifier\Message\SmsMessage;

class NotificationTrackingMiddlewareTest extends TestCase
{
    private NotificationTrackingMiddleware $middleware;

    protected function setUp(): void
    {
        $this->middleware = new NotificationTrackingMiddleware();
    }

    public function testImplementsMiddlewareInterface(): void
    {
        $this->assertInstanceOf(MiddlewareInterface::class, $this->middleware);
    }

    public function testAddsStampToEmailMessage(): void
    {
        $email = new Email();
        $email->from('test@example.com')
            ->to('recipient@example.com')
            ->subject('Test Email');

        $message = new SendEmailMessage($email);
        $envelope = new Envelope($message);

        // Mock the stack to capture the envelope with the stamp
        $capturedEnvelope = null;
        $stack = $this->createMockStack(function (Envelope $env) use (&$capturedEnvelope) {
            $capturedEnvelope = $env;
            return $env;
        });

        $result = $this->middleware->handle($envelope, $stack);

        // Verify stamp was added
        $stamp = $result->last(NotificationTrackingStamp::class);
        $this->assertNotNull($stamp);
        $this->assertInstanceOf(NotificationTrackingStamp::class, $stamp);
        $this->assertNotEmpty($stamp->getId());
    }

    public function testDoesNotProcessNonEmailMessages(): void
    {
        $message = new class {
            public function getMessage(): string
            {
                return 'non-email message';
            }
        };
        $envelope = new Envelope($message);

        $stack = $this->createMockStack(function (Envelope $env) {
            return $env;
        });

        $result = $this->middleware->handle($envelope, $stack);
        
        // Verify no stamp was added to non-email message
        $this->assertNull($result->last(NotificationTrackingStamp::class));
        $this->assertSame($envelope, $result);
    }

    public function testPreservesExistingStamp(): void
    {
        $email = new Email();
        $email->from('test@example.com')
            ->to('recipient@example.com')
            ->subject('Test Email');

        $message = new SendEmailMessage($email);
        $existingStamp = new NotificationTrackingStamp('existing-stamp-id');
        $envelope = new Envelope($message, [$existingStamp]);

        $stack = $this->createMockStack(function (Envelope $env) {
            return $env;
        });

        $result = $this->middleware->handle($envelope, $stack);

        // Verify existing stamp is preserved
        $stamp = $result->last(NotificationTrackingStamp::class);
        $this->assertSame($existingStamp, $stamp);
        $this->assertEquals('existing-stamp-id', $stamp->getId());
    }

    public function testAddsStampIdToEmailHeaders(): void
    {
        $email = new Email();
        $email->from('test@example.com')
            ->to('recipient@example.com')
            ->subject('Test Email');

        $message = new SendEmailMessage($email);
        $envelope = new Envelope($message);

        $stack = $this->createMockStack(function (Envelope $env) {
            return $env;
        });

        $result = $this->middleware->handle($envelope, $stack);

        // Verify stamp ID was added to email headers
        $stamp = $result->last(NotificationTrackingStamp::class);
        $this->assertNotNull($stamp);
        
        $processedMessage = $result->getMessage();
        $processedEmail = $processedMessage->getMessage();
        
        $this->assertTrue($processedEmail->getHeaders()->has('X-Stamp-ID'));
        $this->assertEquals($stamp->getId(), $processedEmail->getHeaders()->get('X-Stamp-ID')->getBodyAsString());
    }

    public function testDoesNotDuplicateHeaders(): void
    {
        $email = new Email();
        $email->from('test@example.com')
            ->to('recipient@example.com')
            ->subject('Test Email');
        
        // Pre-add the header
        $email->getHeaders()->addTextHeader('X-Stamp-ID', 'existing-header-id');

        $message = new SendEmailMessage($email);
        $envelope = new Envelope($message);

        $stack = $this->createMockStack(function (Envelope $env) {
            return $env;
        });

        $result = $this->middleware->handle($envelope, $stack);

        $processedMessage = $result->getMessage();
        $processedEmail = $processedMessage->getMessage();
        
        // Should not duplicate the header
        $headers = iterator_to_array($processedEmail->getHeaders()->all('X-Stamp-ID'));
        $this->assertCount(1, $headers);
        $this->assertEquals('existing-header-id', $headers[0]->getBodyAsString());
    }

    public function testGeneratesUniqueStampIds(): void
    {
        $stampIds = [];
        
        for ($i = 0; $i < 10; $i++) {
            $email = new Email();
            $email->from('test@example.com')
                ->to('recipient@example.com')
                ->subject("Test Email $i");

            $message = new SendEmailMessage($email);
            $envelope = new Envelope($message);

            $stack = $this->createMockStack(function (Envelope $env) {
                return $env;
            });

            $result = $this->middleware->handle($envelope, $stack);
            $stamp = $result->last(NotificationTrackingStamp::class);
            
            $stampIds[] = $stamp->getId();
        }

        // Verify all IDs are unique
        $uniqueIds = array_unique($stampIds);
        $this->assertCount(10, $uniqueIds);
        $this->assertEquals($stampIds, $uniqueIds);
    }

    public function testStampIdIsUlidFormat(): void
    {
        $email = new Email();
        $email->from('test@example.com')
            ->to('recipient@example.com')
            ->subject('Test Email');

        $message = new SendEmailMessage($email);
        $envelope = new Envelope($message);

        $stack = $this->createMockStack(function (Envelope $env) {
            return $env;
        });

        $result = $this->middleware->handle($envelope, $stack);
        $stamp = $result->last(NotificationTrackingStamp::class);
        
        // ULID should be 26 characters long and contain only specific characters
        $stampId = $stamp->getId();
        $this->assertEquals(26, strlen($stampId));
        $this->assertMatchesRegularExpression('/^[0-9A-Z]{26}$/', $stampId);
    }

    public function testAddsStampToSmsMessage(): void
    {
        $message = new SmsMessage('+1234567890', 'Test SMS message');
        $envelope = new Envelope($message);

        $stack = $this->createMockStack(function (Envelope $env) {
            return $env;
        });

        $result = $this->middleware->handle($envelope, $stack);

        // Verify stamp was added
        $stamp = $result->last(NotificationTrackingStamp::class);
        $this->assertNotNull($stamp);
        $this->assertInstanceOf(NotificationTrackingStamp::class, $stamp);
        $this->assertNotEmpty($stamp->getId());
    }

    public function testAddsStampToPushMessage(): void
    {
        $message = new PushMessage('Test Push', 'This is a test push notification');
        $envelope = new Envelope($message);

        $stack = $this->createMockStack(function (Envelope $env) {
            return $env;
        });

        $result = $this->middleware->handle($envelope, $stack);

        // Verify stamp was added
        $stamp = $result->last(NotificationTrackingStamp::class);
        $this->assertNotNull($stamp);
        $this->assertInstanceOf(NotificationTrackingStamp::class, $stamp);
        $this->assertNotEmpty($stamp->getId());
    }

    public function testAddsStampToChatMessage(): void
    {
        $message = new ChatMessage('Test chat message');
        $envelope = new Envelope($message);

        $stack = $this->createMockStack(function (Envelope $env) {
            return $env;
        });

        $result = $this->middleware->handle($envelope, $stack);

        // Verify stamp was added
        $stamp = $result->last(NotificationTrackingStamp::class);
        $this->assertNotNull($stamp);
        $this->assertInstanceOf(NotificationTrackingStamp::class, $stamp);
        $this->assertNotEmpty($stamp->getId());
    }

    public function testAddsStampToNotifierEmailMessage(): void
    {
        $email = new Email();
        $email->from('test@example.com')
            ->to('recipient@example.com')
            ->subject('Test Subject');
        
        $message = new EmailMessage($email);
        $envelope = new Envelope($message);

        $stack = $this->createMockStack(function (Envelope $env) {
            return $env;
        });

        $result = $this->middleware->handle($envelope, $stack);

        // Verify stamp was added
        $stamp = $result->last(NotificationTrackingStamp::class);
        $this->assertNotNull($stamp);
        $this->assertInstanceOf(NotificationTrackingStamp::class, $stamp);
        $this->assertNotEmpty($stamp->getId());
    }

    public function testAddsStampToDesktopMessage(): void
    {
        $message = new DesktopMessage('Test Desktop', 'This is a test desktop notification');
        $envelope = new Envelope($message);

        $stack = $this->createMockStack(function (Envelope $env) {
            return $env;
        });

        $result = $this->middleware->handle($envelope, $stack);

        // Verify stamp was added
        $stamp = $result->last(NotificationTrackingStamp::class);
        $this->assertNotNull($stamp);
        $this->assertInstanceOf(NotificationTrackingStamp::class, $stamp);
        $this->assertNotEmpty($stamp->getId());
    }

    public function testPreservesExistingStampForNotifierMessages(): void
    {
        $message = new SmsMessage('+1234567890', 'Test SMS message');
        $existingStamp = new NotificationTrackingStamp('existing-notifier-stamp-id');
        $envelope = new Envelope($message, [$existingStamp]);

        $stack = $this->createMockStack(function (Envelope $env) {
            return $env;
        });

        $result = $this->middleware->handle($envelope, $stack);

        // Verify existing stamp is preserved
        $stamp = $result->last(NotificationTrackingStamp::class);
        $this->assertSame($existingStamp, $stamp);
        $this->assertEquals('existing-notifier-stamp-id', $stamp->getId());
    }

    public function testAddsNotificationTrackerHeaderToEmailMessages(): void
    {
        $email = new Email();
        $email->from('test@example.com')
            ->to('recipient@example.com')
            ->subject('Test Email');

        $message = new SendEmailMessage($email);
        $envelope = new Envelope($message);

        $stack = $this->createMockStack(function (Envelope $env) {
            return $env;
        });

        $result = $this->middleware->handle($envelope, $stack);

        // Verify additional tracking header was added
        $processedMessage = $result->getMessage();
        $this->assertInstanceOf(SendEmailMessage::class, $processedMessage);
        
        $processedEmail = $processedMessage->getMessage();
        $this->assertTrue($processedEmail->getHeaders()->has('X-Notification-Tracker'));
        $this->assertEquals('enabled', $processedEmail->getHeaders()->get('X-Notification-Tracker')->getBody());
    }

    /**
     * @dataProvider notificationMessageProvider
     */
    public function testHandlesAllNotificationMessageTypes(object $message, string $expectedClass): void
    {
        $envelope = new Envelope($message);

        $stack = $this->createMockStack(function (Envelope $env) {
            return $env;
        });

        $result = $this->middleware->handle($envelope, $stack);

        // Verify stamp was added to all notification message types
        $stamp = $result->last(NotificationTrackingStamp::class);
        $this->assertNotNull($stamp, "Failed to add stamp to {$expectedClass}");
        $this->assertInstanceOf(NotificationTrackingStamp::class, $stamp);
        $this->assertNotEmpty($stamp->getId());
    }

    public static function notificationMessageProvider(): array
    {
        $email = new Email();
        $email->from('test@example.com')->to('recipient@example.com')->subject('Test');

        $notifierEmail = new Email();
        $notifierEmail->from('test@example.com')->to('recipient@example.com')->subject('Test');

        return [
            'SendEmailMessage' => [new SendEmailMessage($email), SendEmailMessage::class],
            'SmsMessage' => [new SmsMessage('+1234567890', 'Test SMS'), SmsMessage::class],
            'PushMessage' => [new PushMessage('Title', 'Content'), PushMessage::class],
            'ChatMessage' => [new ChatMessage('Test chat'), ChatMessage::class],
            'EmailMessage' => [new EmailMessage($notifierEmail), EmailMessage::class],
            'DesktopMessage' => [new DesktopMessage('Title', 'Content'), DesktopMessage::class],
        ];
    }

    private function createMockStack(callable $handleCallback): StackInterface
    {
        $nextMiddleware = $this->createMock(MiddlewareInterface::class);
        $nextMiddleware->method('handle')->willReturnCallback($handleCallback);
        
        $stack = $this->createMock(StackInterface::class);
        $stack->method('next')->willReturn($nextMiddleware);
        
        /** @var StackInterface $stack */
        return $stack;
    }
}
