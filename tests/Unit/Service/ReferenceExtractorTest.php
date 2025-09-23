<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Tests\Unit\Service;

use Nkamuo\NotificationTrackerBundle\Service\ReferenceExtractor;
use Nkamuo\NotificationTrackerBundle\Entity\Notification;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Email;

class ReferenceExtractorTest extends TestCase
{
    private ReferenceExtractor $referenceExtractor;

    protected function setUp(): void
    {
        /** @var LoggerInterface $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $this->referenceExtractor = new ReferenceExtractor($logger);
    }

    public function testExtractFromEmail(): void
    {
        $email = new Email();
        $email->getHeaders()->addTextHeader('X-Notification-Ref-order_id', '12345');
        $email->getHeaders()->addTextHeader('X-Notification-Ref-customer_id', 'cust_67890');
        $email->getHeaders()->addTextHeader('Other-Header', 'ignored');

        $refs = $this->referenceExtractor->extractFromEmail($email);

        $this->assertCount(2, $refs);
        $this->assertEquals('12345', $refs['order_id']);
        $this->assertEquals('cust_67890', $refs['customer_id']);
        $this->assertArrayNotHasKey('Other-Header', $refs);
    }

    public function testExtractFromContext(): void
    {
        $email = new Email();
        $email->getHeaders()->addTextHeader('X-Notification-Ref-shipment_id', 'ship_123');

        $context = [
            'refs' => [
                'order_id' => '12345',
                'customer_id' => 'cust_67890'
            ],
            'email' => $email,
            'headers' => [
                'X-Notification-Ref-product_id' => 'prod_xyz'
            ]
        ];

        $refs = $this->referenceExtractor->extractFromContext($context);

        $this->assertCount(4, $refs);
        $this->assertEquals('12345', $refs['order_id']);
        $this->assertEquals('cust_67890', $refs['customer_id']);
        $this->assertEquals('ship_123', $refs['shipment_id']);
        $this->assertEquals('prod_xyz', $refs['product_id']);
    }

    public function testExtractFromContextWithoutRefs(): void
    {
        $context = [];
        $refs = $this->referenceExtractor->extractFromContext($context);
        $this->assertEmpty($refs);
    }

    public function testApplyToNotification(): void
    {
        $notification = new Notification();
        $refs = [
            'order_id' => '12345',
            'customer_id' => 'cust_67890'
        ];

        $count = $this->referenceExtractor->applyToNotification($notification, $refs);

        $this->assertEquals(2, $count);
        $this->assertEquals('12345', $notification->getRef('order_id'));
        $this->assertEquals('cust_67890', $notification->getRef('customer_id'));
    }

    public function testAddToEmail(): void
    {
        $email = new Email();
        $refs = [
            'order_id' => '12345',
            'customer_id' => 'cust_67890'
        ];

        $resultEmail = $this->referenceExtractor->addToEmail($email, $refs);

        $this->assertSame($email, $resultEmail);
        
        // Check headers were added
        $headers = $email->getHeaders();
        $this->assertTrue($headers->has('X-Notification-Ref-order_id'));
        $this->assertTrue($headers->has('X-Notification-Ref-customer_id'));
        
        $this->assertEquals('12345', $headers->get('X-Notification-Ref-order_id')->getBodyAsString());
        $this->assertEquals('cust_67890', $headers->get('X-Notification-Ref-customer_id')->getBodyAsString());
    }

    public function testCreateHeaders(): void
    {
        $refs = [
            'order_id' => '12345',
            'customer_id' => 'cust_67890'
        ];

        $headers = $this->referenceExtractor->createHeaders($refs);

        $this->assertCount(2, $headers);
        $this->assertEquals('12345', $headers['X-Notification-Ref-order_id']);
        $this->assertEquals('cust_67890', $headers['X-Notification-Ref-customer_id']);
    }

    public function testGetHeaderPrefix(): void
    {
        $prefix = $this->referenceExtractor->getHeaderPrefix();
        $this->assertEquals('X-Notification-Ref-', $prefix);
    }

    public function testIsValidRefKey(): void
    {
        $this->assertTrue($this->referenceExtractor->isValidRefKey('order_id'));
        $this->assertTrue($this->referenceExtractor->isValidRefKey('customer-id'));
        $this->assertTrue($this->referenceExtractor->isValidRefKey('product.type'));
        $this->assertTrue($this->referenceExtractor->isValidRefKey('abc123'));
        
        $this->assertFalse($this->referenceExtractor->isValidRefKey('order id')); // space
        $this->assertFalse($this->referenceExtractor->isValidRefKey('order@id')); // @
        $this->assertFalse($this->referenceExtractor->isValidRefKey('order/id')); // /
    }

    public function testSanitizeRefKey(): void
    {
        $this->assertEquals('order_id', $this->referenceExtractor->sanitizeRefKey('order_id'));
        $this->assertEquals('order_id', $this->referenceExtractor->sanitizeRefKey('order id'));
        $this->assertEquals('order_id', $this->referenceExtractor->sanitizeRefKey('order@id'));
        $this->assertEquals('customer_name_here', $this->referenceExtractor->sanitizeRefKey('customer name here'));
    }

    public function testExtractFromEmailWithEmptyValues(): void
    {
        $email = new Email();
        $email->getHeaders()->addTextHeader('X-Notification-Ref-order_id', '12345');
        $email->getHeaders()->addTextHeader('X-Notification-Ref-empty', '');
        $email->getHeaders()->addTextHeader('X-Notification-Ref-', 'no-key');

        $refs = $this->referenceExtractor->extractFromEmail($email);

        $this->assertCount(1, $refs);
        $this->assertEquals('12345', $refs['order_id']);
        $this->assertArrayNotHasKey('empty', $refs);
        $this->assertArrayNotHasKey('', $refs);
    }

    public function testExtractFromContextMergesAllSources(): void
    {
        $email = new Email();
        $email->getHeaders()->addTextHeader('X-Notification-Ref-from_email', 'email_value');

        $context = [
            'refs' => [
                'from_refs' => 'refs_value',
                'duplicate' => 'refs_original'
            ],
            'email' => $email,
            'headers' => [
                'X-Notification-Ref-from_headers' => 'headers_value',
                'X-Notification-Ref-duplicate' => 'headers_override'
            ]
        ];

        $refs = $this->referenceExtractor->extractFromContext($context);

        $this->assertCount(4, $refs);
        $this->assertEquals('refs_value', $refs['from_refs']);
        $this->assertEquals('email_value', $refs['from_email']);
        $this->assertEquals('headers_value', $refs['from_headers']);
        // Headers should override refs due to merge order
        $this->assertEquals('headers_override', $refs['duplicate']);
    }
}
