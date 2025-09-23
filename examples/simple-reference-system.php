<?php

declare(strict_types=1);

/**
 * Example showing how to use the simple reference system
 */

use Nkamuo\NotificationTrackerBundle\Entity\Notification;
use Nkamuo\NotificationTrackerBundle\Entity\Message;
use Nkamuo\NotificationTrackerBundle\Service\ReferenceExtractor;
use Symfony\Component\Mime\Email;

// Example 1: Using refs directly on notifications
function exampleDirectUsage()
{
    $notification = new Notification();
    
    // Set individual references
    $notification->setRef('order_id', '12345');
    $notification->setRef('customer_id', 'cust_67890');
    $notification->setRef('shipment_id', 'ship_abc123');
    
    // Get references
    $orderId = $notification->getRef('order_id'); // '12345'
    $customerId = $notification->getRef('customer_id'); // 'cust_67890'
    
    // Check if reference exists
    if ($notification->hasRef('order_id')) {
        echo "Order ID: " . $notification->getRef('order_id');
    }
    
    // Get all references
    $allRefs = $notification->getRefs();
    // ['order_id' => '12345', 'customer_id' => 'cust_67890', 'shipment_id' => 'ship_abc123']
    
    // Set multiple references at once
    $notification->setRefs([
        'product_id' => 'prod_xyz789',
        'category' => 'electronics'
    ]);
    
    // Remove a reference
    $notification->removeRef('shipment_id');
    
    // Clear all references
    $notification->clearRefs();
}

// Example 2: Using constants for better code maintenance
class OrderNotifications
{
    public const ORDER_REF = 'order_id';
    public const CUSTOMER_REF = 'customer_id'; 
    public const SHIPMENT_REF = 'shipment_id';
    
    public static function createOrderNotification(string $orderId, string $customerId): Notification
    {
        $notification = new Notification();
        $notification->setRef(self::ORDER_REF, $orderId);
        $notification->setRef(self::CUSTOMER_REF, $customerId);
        
        return $notification;
    }
    
    public static function addShipmentRef(Notification $notification, string $shipmentId): void
    {
        $notification->setRef(self::SHIPMENT_REF, $shipmentId);
    }
}

// Example 3: Email headers - automatic extraction
function exampleEmailHeaders(ReferenceExtractor $extractor)
{
    // Create email with reference headers
    $email = new Email();
    $email->from('shop@example.com')
          ->to('customer@example.com')
          ->subject('Order Confirmation');
    
    // Add reference headers manually
    $email->getHeaders()->addTextHeader('X-Notification-Ref-order_id', '12345');
    $email->getHeaders()->addTextHeader('X-Notification-Ref-customer_id', 'cust_67890');
    
    // Or use the service to add multiple at once
    $refs = [
        'order_id' => '12345',
        'customer_id' => 'cust_67890',
        'product_id' => 'prod_xyz789'
    ];
    $extractor->addToEmail($email, $refs);
    
    // Extract references from email (done automatically by event listener)
    $extractedRefs = $extractor->extractFromEmail($email);
    // ['order_id' => '12345', 'customer_id' => 'cust_67890', 'product_id' => 'prod_xyz789']
}

// Example 4: Event-driven automatic extraction
function exampleEventDriven()
{
    // When you create a notification with context containing refs or email,
    // the ReferenceExtractionSubscriber will automatically extract and apply them
    
    $context = [
        'refs' => [
            'order_id' => '12345',
            'customer_id' => 'cust_67890'
        ],
        'email' => $email, // Email object with X-Notification-Ref-* headers
        'headers' => [
            'X-Notification-Ref-shipment_id' => 'ship_abc123'
        ]
    ];
    
    // When NotificationCreatedEvent is dispatched with this context,
    // all references will be automatically extracted and applied
}

// Example 5: Working with Messages (same API)
function exampleMessageRefs()
{
    $message = new Message();
    
    // Same API as notifications
    $message->setRef('order_id', '12345');
    $message->setRef('delivery_attempt', '1');
    
    $orderId = $message->getRef('order_id');
    $allRefs = $message->getRefs();
    
    // Messages can have different refs than their notification
    // useful for tracking delivery-specific data
}

// Example 6: Finding notifications/messages by references
function exampleQueryByRefs()
{
    // You can search in metadata using JSON queries
    // This depends on your database, but here's a general approach:
    
    // Find notifications with specific order_id
    // SELECT * FROM notification WHERE JSON_EXTRACT(metadata, '$.refs.order_id') = '12345'
    
    // Find all notifications for a customer
    // SELECT * FROM notification WHERE JSON_EXTRACT(metadata, '$.refs.customer_id') = 'cust_67890'
    
    // This is much simpler than complex JOIN queries!
}

// Example 7: Integration with business logic
class OrderEventListener
{
    public function onOrderShipped(OrderShippedEvent $event): void
    {
        $order = $event->getOrder();
        
        // Find notifications related to this order
        // You can implement a service to find by refs
        $notifications = $this->findNotificationsByRef('order_id', $order->getId());
        
        // Update them with shipment info
        foreach ($notifications as $notification) {
            $notification->setRef('shipment_id', $event->getShipmentId());
            $notification->setRef('tracking_number', $event->getTrackingNumber());
        }
    }
}
