<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Tests\Integration;

use Nkamuo\NotificationTrackerBundle\Entity\Notification;
use Nkamuo\NotificationTrackerBundle\Entity\Message;
use Nkamuo\NotificationTrackerBundle\Event\NotificationCreatedEvent;
use Nkamuo\NotificationTrackerBundle\Event\MessageCreatedEvent;
use Nkamuo\NotificationTrackerBundle\EventSubscriber\ReferenceExtractionSubscriber;
use Nkamuo\NotificationTrackerBundle\Service\ReferenceExtractor;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Email;

class SimpleReferenceSystemIntegrationTest extends TestCase
{
    public function testNotificationReferenceMethodsWork(): void
    {
        $notification = new Notification();
        
        // Test setting individual refs
        $notification->setRef('order_id', '12345');
        $notification->setRef('customer_id', 'cust_67890');
        
        // Test getting refs
        $this->assertEquals('12345', $notification->getRef('order_id'));
        $this->assertEquals('cust_67890', $notification->getRef('customer_id'));
        $this->assertNull($notification->getRef('nonexistent'));
        $this->assertEquals('default', $notification->getRef('nonexistent', 'default'));
        
        // Test has ref
        $this->assertTrue($notification->hasRef('order_id'));
        $this->assertFalse($notification->hasRef('nonexistent'));
        
        // Test get all refs
        $allRefs = $notification->getRefs();
        $this->assertCount(2, $allRefs);
        $this->assertEquals('12345', $allRefs['order_id']);
        $this->assertEquals('cust_67890', $allRefs['customer_id']);
        
        // Test set multiple refs (replaces existing)
        $notification->setRefs([
            'product_id' => 'prod_xyz',
            'category' => 'electronics'
        ]);
        
        $this->assertCount(2, $notification->getRefs());
        $this->assertEquals('prod_xyz', $notification->getRef('product_id'));
        $this->assertFalse($notification->hasRef('order_id')); // Replaced
        
        // Test remove ref
        $notification->removeRef('category');
        $this->assertFalse($notification->hasRef('category'));
        $this->assertCount(1, $notification->getRefs());
        
        // Test clear refs
        $notification->clearRefs();
        $this->assertCount(0, $notification->getRefs());
    }

    public function testMessageReferenceMethodsWork(): void
    {
        $message = $this->createMockMessage();
        
        // Test same API as notification
        $message->setRef('order_id', '12345');
        $message->setRef('delivery_attempt', '1');
        
        $this->assertEquals('12345', $message->getRef('order_id'));
        $this->assertEquals('1', $message->getRef('delivery_attempt'));
        
        $allRefs = $message->getRefs();
        $this->assertCount(2, $allRefs);
        
        // Test messages can have different refs than notifications
        $message->setRef('message_specific', 'value');
        $this->assertTrue($message->hasRef('message_specific'));
    }

    public function testEventSubscriberExtractsReferences(): void
    {
        /** @var LoggerInterface $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $extractor = new ReferenceExtractor($logger);
        $subscriber = new ReferenceExtractionSubscriber($extractor, $logger);
        
        // Test notification event
        $notification = new Notification();
        $email = new Email();
        $email->getHeaders()->addTextHeader('X-Notification-Ref-order_id', '12345');
        
        $context = [
            'refs' => ['customer_id' => 'cust_67890'],
            'email' => $email,
            'headers' => ['X-Notification-Ref-product_id' => 'prod_xyz']
        ];
        
        $event = new NotificationCreatedEvent($notification, $context);
        $subscriber->onNotificationCreated($event);
        
        // Verify refs were extracted
        $this->assertEquals('12345', $notification->getRef('order_id'));
        $this->assertEquals('cust_67890', $notification->getRef('customer_id'));
        $this->assertEquals('prod_xyz', $notification->getRef('product_id'));
        
        // Test message event
        $message = $this->createMockMessage();
        $messageEvent = new MessageCreatedEvent($message, $context);
        $subscriber->onMessageCreated($messageEvent);
        
        // Verify refs were extracted for message too
        $this->assertEquals('12345', $message->getRef('order_id'));
        $this->assertEquals('cust_67890', $message->getRef('customer_id'));
        $this->assertEquals('prod_xyz', $message->getRef('product_id'));
    }

    public function testEmailHeaderIntegration(): void
    {
        /** @var LoggerInterface $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $extractor = new ReferenceExtractor($logger);
        
        // Test adding refs to email
        $email = new Email();
        $refs = [
            'order_id' => '12345',
            'customer_id' => 'cust_67890'
        ];
        
        $extractor->addToEmail($email, $refs);
        
        // Verify headers were added
        $headers = $email->getHeaders();
        $this->assertTrue($headers->has('X-Notification-Ref-order_id'));
        $this->assertTrue($headers->has('X-Notification-Ref-customer_id'));
        $this->assertEquals('12345', $headers->get('X-Notification-Ref-order_id')->getBodyAsString());
        
        // Test extracting refs from email
        $extractedRefs = $extractor->extractFromEmail($email);
        $this->assertEquals($refs, $extractedRefs);
    }

    public function testReferenceDataPersistence(): void
    {
        $notification = new Notification();
        $notification->setRef('order_id', '12345');
        $notification->setRef('customer_id', 'cust_67890');
        
        // Verify metadata structure
        $metadata = $notification->getMetadata();
        $this->assertArrayHasKey('refs', $metadata);
        $this->assertIsArray($metadata['refs']);
        $this->assertEquals('12345', $metadata['refs']['order_id']);
        $this->assertEquals('cust_67890', $metadata['refs']['customer_id']);
        
        // Test that existing metadata is preserved
        $notification->setMetadata(['existing' => 'data']);
        $notification->setRef('new_ref', 'value');
        
        $updatedMetadata = $notification->getMetadata();
        $this->assertEquals('data', $updatedMetadata['existing']);
        $this->assertEquals('value', $updatedMetadata['refs']['new_ref']);
    }

    private function createMockMessage(): Message
    {
        // Create a mock message since Message is abstract
        return new class extends Message {
            public function __construct()
            {
                // Initialize required properties for testing
                $this->setMetadata([]);
            }

            public function getSubject(): ?string
            {
                return 'Test Subject';
            }

            public function getType(): string
            {
                return 'test';
            }
        };
    }
}
