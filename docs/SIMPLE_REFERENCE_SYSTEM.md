# Simple Reference System Usage Guide

The notification tracker now includes a simple reference system that allows you to link notifications and messages to business entities using metadata-based references.

## Key Features

- **Simple API**: Use `setRef()`, `getRef()`, `hasRef()`, `removeRef()`, `getRefs()`, `setRefs()`, `clearRefs()`
- **Metadata Storage**: References stored in nested metadata structure `metadata['refs'][key] = value`
- **Email Header Integration**: Automatic extraction from `X-Notification-Ref-{key}` headers
- **Event-Driven**: Automatic processing through existing event system
- **String-Based Keys**: Use either string literals or constants for maintainability

## Basic Usage

### Setting and Getting References

```php
use Nkamuo\NotificationTrackerBundle\Entity\Notification;

$notification = new Notification();

// Set individual references
$notification->setRef('order_id', '12345');
$notification->setRef('customer_id', 'cust_67890');
$notification->setRef('shipment_id', 'ship_abc123');

// Get references
$orderId = $notification->getRef('order_id'); // '12345'
$customerId = $notification->getRef('customer_id', 'default'); // with default value

// Check if reference exists
if ($notification->hasRef('order_id')) {
    echo "Order ID: " . $notification->getRef('order_id');
}

// Get all references
$allRefs = $notification->getRefs();
// Returns: ['order_id' => '12345', 'customer_id' => 'cust_67890', 'shipment_id' => 'ship_abc123']

// Set multiple references at once
$notification->setRefs([
    'product_id' => 'prod_xyz789',
    'category' => 'electronics'
]);

// Remove a reference
$notification->removeRef('shipment_id');

// Clear all references
$notification->clearRefs();
```

### Using Constants for Better Maintainability

```php
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
```

## Email Header Integration

### Automatic Extraction

When you create notifications or messages with email context, references are automatically extracted from `X-Notification-Ref-*` headers:

```php
use Symfony\Component\Mime\Email;

$email = new Email();
$email->from('shop@example.com')
      ->to('customer@example.com')
      ->subject('Order Confirmation');

// Add reference headers
$email->getHeaders()->addTextHeader('X-Notification-Ref-order_id', '12345');
$email->getHeaders()->addTextHeader('X-Notification-Ref-customer_id', 'cust_67890');

// When this email is processed through the notification system,
// references will be automatically extracted and applied
```

### Manual Reference Addition

```php
use Nkamuo\NotificationTrackerBundle\Service\ReferenceExtractor;

/** @var ReferenceExtractor $extractor */
$refs = [
    'order_id' => '12345',
    'customer_id' => 'cust_67890',
    'product_id' => 'prod_xyz789'
];

// Add references as headers to an email
$extractor->addToEmail($email, $refs);

// Or get headers array for manual addition
$headers = $extractor->createHeaders($refs);
// Returns: ['X-Notification-Ref-order_id' => '12345', ...]
```

## Event-Driven Automatic Extraction

The system automatically extracts references when notifications or messages are created through the event system:

```php
// Context with various reference sources
$context = [
    // Direct references
    'refs' => [
        'order_id' => '12345',
        'customer_id' => 'cust_67890'
    ],
    
    // Email with headers (automatically extracted)
    'email' => $emailWithHeaders,
    
    // Raw headers
    'headers' => [
        'X-Notification-Ref-shipment_id' => 'ship_abc123'
    ]
];

// When NotificationCreatedEvent or MessageCreatedEvent is dispatched,
// the ReferenceExtractionSubscriber automatically processes all these sources
```

## Database Queries

Since references are stored in JSON metadata, you can query them efficiently:

### MySQL/MariaDB

```sql
-- Find notifications with specific order_id
SELECT * FROM notification 
WHERE JSON_EXTRACT(metadata, '$.refs.order_id') = '12345';

-- Find all notifications for a customer
SELECT * FROM notification 
WHERE JSON_EXTRACT(metadata, '$.refs.customer_id') = 'cust_67890';

-- Find notifications with any order reference
SELECT * FROM notification 
WHERE JSON_EXTRACT(metadata, '$.refs.order_id') IS NOT NULL;
```

### PostgreSQL

```sql
-- Find notifications with specific order_id
SELECT * FROM notification 
WHERE metadata->'refs'->>'order_id' = '12345';

-- Find all notifications for a customer
SELECT * FROM notification 
WHERE metadata->'refs'->>'customer_id' = 'cust_67890';

-- Find notifications with any order reference
SELECT * FROM notification 
WHERE metadata->'refs'->'order_id' IS NOT NULL;
```

## Repository Methods

You can extend your repositories to add convenience methods:

```php
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Nkamuo\NotificationTrackerBundle\Entity\Notification;

class NotificationRepository extends ServiceEntityRepository
{
    public function findByRef(string $key, string $value): array
    {
        return $this->createQueryBuilder('n')
            ->where('JSON_EXTRACT(n.metadata, :path) = :value')
            ->setParameter('path', '$.refs.' . $key)
            ->setParameter('value', $value)
            ->getQuery()
            ->getResult();
    }
    
    public function findByOrderId(string $orderId): array
    {
        return $this->findByRef('order_id', $orderId);
    }
    
    public function findByCustomerId(string $customerId): array
    {
        return $this->findByRef('customer_id', $customerId);
    }
}
```

## Business Logic Integration

### Event Listeners

```php
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private EntityManagerInterface $entityManager
    ) {}
    
    public static function getSubscribedEvents(): array
    {
        return [
            'app.order.shipped' => 'onOrderShipped',
        ];
    }
    
    public function onOrderShipped(OrderShippedEvent $event): void
    {
        $order = $event->getOrder();
        
        // Find notifications related to this order
        $notifications = $this->notificationRepository->findByOrderId($order->getId());
        
        // Update them with shipment info
        foreach ($notifications as $notification) {
            $notification->setRef('shipment_id', $event->getShipmentId());
            $notification->setRef('tracking_number', $event->getTrackingNumber());
            $notification->setRef('shipped_at', $event->getShippedAt()->format('c'));
        }
        
        $this->entityManager->flush();
    }
}
```

### Service Classes

```php
use Nkamuo\NotificationTrackerBundle\Service\NotificationTracker;

class OrderNotificationService
{
    public function __construct(
        private NotificationTracker $tracker,
        private NotificationRepository $repository
    ) {}
    
    public function createOrderConfirmation(Order $order): Notification
    {
        $context = [
            'refs' => [
                'order_id' => $order->getId(),
                'customer_id' => $order->getCustomerId(),
                'total_amount' => $order->getTotal()
            ]
        ];
        
        return $this->tracker->createNotification(
            'order_confirmation',
            'Order Confirmation',
            'Your order has been confirmed',
            $context
        );
    }
    
    public function getOrderNotifications(string $orderId): array
    {
        return $this->repository->findByOrderId($orderId);
    }
}
```

## Best Practices

1. **Use Constants**: Define reference keys as constants for better maintainability
2. **Consistent Naming**: Use consistent naming patterns (e.g., `entity_id`, `entity_type`)
3. **Validation**: Validate reference values before setting them
4. **Indexing**: Consider adding database indexes on commonly queried reference paths
5. **Documentation**: Document your reference schema for team members

## Migration from EntityReference

If you were using the old EntityReference system, here's how to migrate:

```php
// Old way (complex)
$entityRef = new EntityReference();
$entityRef->setEntityClass(Order::class);
$entityRef->setEntityId('12345');
$notification->addEntityReference($entityRef);

// New way (simple)
$notification->setRef('order_id', '12345');
```

The simple reference system provides the same functionality with much less complexity and better performance.
