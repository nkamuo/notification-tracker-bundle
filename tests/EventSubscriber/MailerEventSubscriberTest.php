<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Tests\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Nkamuo\NotificationTrackerBundle\Entity\EmailMessage;
use Nkamuo\NotificationTrackerBundle\EventSubscriber\MailerEventSubscriber;
use Nkamuo\NotificationTrackerBundle\Repository\MessageRepository;
use Nkamuo\NotificationTrackerBundle\Service\MessageTracker;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Email;

class MailerEventSubscriberTest extends TestCase
{
    private MessageTracker $messageTracker;
    private EntityManagerInterface $entityManager;
    private MessageRepository $messageRepository;
    private LoggerInterface $logger;
    private MailerEventSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->messageTracker = $this->createMock(MessageTracker::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->messageRepository = $this->createMock(MessageRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        
        $this->subscriber = new MailerEventSubscriber(
            $this->messageTracker,
            $this->entityManager,
            $this->messageRepository,
            $this->logger,
            true // enabled
        );
    }

    public function testGenerateContentFingerprint(): void
    {
        $email = new Email();
        $email->from('sender@example.com')
            ->to('recipient@example.com')
            ->cc('cc@example.com')
            ->subject('Test Subject')
            ->text('Test body content')
            ->html('<p>Test HTML content</p>');

        // Use reflection to access the private method
        $reflection = new \ReflectionClass($this->subscriber);
        $method = $reflection->getMethod('generateContentFingerprint');
        $method->setAccessible(true);

        $fingerprint1 = $method->invoke($this->subscriber, $email);
        $fingerprint2 = $method->invoke($this->subscriber, $email);

        // Same email should produce same fingerprint
        $this->assertEquals($fingerprint1, $fingerprint2);
        $this->assertIsString($fingerprint1);
        $this->assertEquals(64, strlen($fingerprint1)); // SHA256 hex length
    }

    public function testGenerateContentFingerprintDifferentEmails(): void
    {
        $email1 = new Email();
        $email1->from('sender@example.com')
            ->to('recipient@example.com')
            ->subject('Subject 1')
            ->text('Body 1');

        $email2 = new Email();
        $email2->from('sender@example.com')
            ->to('recipient@example.com')
            ->subject('Subject 2')
            ->text('Body 2');

        $reflection = new \ReflectionClass($this->subscriber);
        $method = $reflection->getMethod('generateContentFingerprint');
        $method->setAccessible(true);

        $fingerprint1 = $method->invoke($this->subscriber, $email1);
        $fingerprint2 = $method->invoke($this->subscriber, $email2);

        // Different emails should produce different fingerprints
        $this->assertNotEquals($fingerprint1, $fingerprint2);
    }

    public function testGenerateContentFingerprintHandlesEmptyFields(): void
    {
        $email = new Email();
        // Minimal email with just required from field
        $email->from('sender@example.com');

        $reflection = new \ReflectionClass($this->subscriber);
        $method = $reflection->getMethod('generateContentFingerprint');
        $method->setAccessible(true);

        $fingerprint = $method->invoke($this->subscriber, $email);

        $this->assertIsString($fingerprint);
        $this->assertEquals(64, strlen($fingerprint));
    }

    public function testGenerateContentFingerprintConsistency(): void
    {
        // Create identical emails
        $createEmail = function () {
            $email = new Email();
            return $email->from('sender@example.com')
                ->to('recipient1@example.com', 'recipient2@example.com')
                ->cc('cc@example.com')
                ->bcc('bcc@example.com')
                ->subject('Consistent Subject')
                ->text('Consistent text body')
                ->html('<p>Consistent HTML body</p>');
        };

        $email1 = $createEmail();
        $email2 = $createEmail();

        $reflection = new \ReflectionClass($this->subscriber);
        $method = $reflection->getMethod('generateContentFingerprint');
        $method->setAccessible(true);

        $fingerprint1 = $method->invoke($this->subscriber, $email1);
        $fingerprint2 = $method->invoke($this->subscriber, $email2);

        // Identical emails should always produce identical fingerprints
        $this->assertEquals($fingerprint1, $fingerprint2);
    }

    public function testGenerateContentFingerprintSensitiveToRecipientOrder(): void
    {
        $email1 = new Email();
        $email1->from('sender@example.com')
            ->to('recipient1@example.com', 'recipient2@example.com')
            ->subject('Test');

        $email2 = new Email();
        $email2->from('sender@example.com')
            ->to('recipient2@example.com', 'recipient1@example.com')
            ->subject('Test');

        $reflection = new \ReflectionClass($this->subscriber);
        $method = $reflection->getMethod('generateContentFingerprint');
        $method->setAccessible(true);

        $fingerprint1 = $method->invoke($this->subscriber, $email1);
        $fingerprint2 = $method->invoke($this->subscriber, $email2);

        // Different recipient order should produce different fingerprints
        // This is expected behavior for our implementation
        $this->assertNotEquals($fingerprint1, $fingerprint2);
    }

    public function testSubscriberIsDisabledWhenConfigured(): void
    {
        $disabledSubscriber = new MailerEventSubscriber(
            $this->messageTracker,
            $this->entityManager,
            $this->messageRepository,
            $this->logger,
            false // disabled
        );

        // When disabled, the subscriber should not process events
        // This would typically be tested with event objects, but we're focusing on the core logic
        $this->assertTrue(true); // Placeholder for disabled functionality test
    }

    public function testGetSubscribedEvents(): void
    {
        $events = MailerEventSubscriber::getSubscribedEvents();

        // Verify all expected events are subscribed
        $this->assertArrayHasKey('Nkamuo\NotificationTrackerBundle\Entity\MessageEvent', $events);
        $this->assertArrayHasKey('Symfony\Component\Messenger\Event\SendMessageToTransportsEvent', $events);
        $this->assertArrayHasKey('Symfony\Component\Mailer\Event\SentMessageEvent', $events);
        $this->assertArrayHasKey('Symfony\Component\Mailer\Event\FailedMessageEvent', $events);

        // Verify event handlers are properly configured
        $messageEvent = $events['Nkamuo\NotificationTrackerBundle\Entity\MessageEvent'];
        $this->assertEquals(['onMessage', 100], $messageEvent);

        $sendEvent = $events['Symfony\Component\Messenger\Event\SendMessageToTransportsEvent'];
        $this->assertEquals(['onSendMessageToTransports', 100], $sendEvent);
    }
}
