# Custom Messenger Transport Implementation Plan

## 🎯 Overview

Create a custom Symfony Messenger transport specifically designed for notification tracking that will:
- **Queue all mailer and notifier messages** through our tracking system
- **Provide richer analytics** with detailed queue metrics, processing times, and failure analysis
- **Enable better control** over message delivery, retry logic, and prioritization
- **Integrate seamlessly** with the existing notification tracker entities and analytics system

## 🏗️ Architecture Design

### 1. Custom Transport Components

```
NotificationTrackerTransport/
├── Transport/
│   ├── NotificationTrackerTransport.php      # Main transport implementation
│   ├── NotificationTrackerSender.php         # Message sender
│   ├── NotificationTrackerReceiver.php       # Message receiver
│   └── NotificationTrackerTransportFactory.php # Transport factory
├── Message/
│   ├── TrackedEmailMessage.php               # Wrapper for email messages
│   ├── TrackedNotifierMessage.php            # Wrapper for notifier messages
│   └── QueuedMessageInterface.php            # Common interface
├── Serializer/
│   ├── TrackedMessageSerializer.php          # Custom serializer
│   └── MessagePayloadEncoder.php             # Payload encoding
├── Storage/
│   ├── QueueStorageInterface.php             # Storage abstraction
│   ├── DoctrineQueueStorage.php              # Database storage
│   └── RedisQueueStorage.php                 # Redis storage option
└── Handler/
    ├── TrackedEmailMessageHandler.php        # Process tracked emails
    ├── TrackedNotifierMessageHandler.php     # Process tracked notifications
    └── QueueMonitoringHandler.php            # Monitor queue health
```

### 2. Enhanced Entities

#### QueuedMessage Entity
```php
#[ORM\Entity]
#[ORM\Table(name: 'notification_tracker_queued_messages')]
class QueuedMessage
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid')]
    private Ulid $id;
    
    #[ORM\Column(type: 'string')]
    private string $messageType; // email, notifier, sms, etc.
    
    #[ORM\Column(type: 'json')]
    private array $serializedMessage;
    
    #[ORM\Column(type: 'string')]
    private string $status; // queued, processing, completed, failed
    
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $queuedAt;
    
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;
    
    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $attempts = 0;
    
    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $priority = 0;
    
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $transportName = null;
    
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = null;
    
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $lastError = null;
    
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $nextRetryAt = null;
    
    // Relationship to tracked message (when processed)
    #[ORM\OneToOne(targetEntity: Message::class)]
    #[ORM\JoinColumn(name: 'message_id', referencedColumnName: 'id', nullable: true)]
    private ?Message $trackedMessage = null;
}
```

### 3. Transport Implementation

#### Main Transport Class
```php
class NotificationTrackerTransport implements TransportInterface
{
    public function __construct(
        private QueueStorageInterface $storage,
        private MessageTracker $messageTracker,
        private LoggerInterface $logger,
        private array $options = []
    ) {}
    
    public function get(): iterable
    {
        // Retrieve messages from queue storage
        return $this->storage->getMessages($this->options['batch_size'] ?? 10);
    }
    
    public function ack(Envelope $envelope): void
    {
        // Mark message as successfully processed
        $queuedMessage = $this->getQueuedMessage($envelope);
        $this->storage->ack($queuedMessage);
        
        // Update analytics
        $this->updateProcessingMetrics($queuedMessage, 'completed');
    }
    
    public function reject(Envelope $envelope): void
    {
        // Handle message rejection with retry logic
        $queuedMessage = $this->getQueuedMessage($envelope);
        $this->handleRetry($queuedMessage, $envelope);
    }
    
    public function send(Envelope $envelope): Envelope
    {
        // Queue the message with tracking
        $queuedMessage = $this->createQueuedMessage($envelope);
        $this->storage->store($queuedMessage);
        
        // Add tracking stamps
        return $envelope->with(
            new NotificationTrackingStamp($queuedMessage->getId()),
            new TransportNameStamp($this->getTransportName())
        );
    }
}
```

### 4. Message Wrappers

#### TrackedEmailMessage
```php
class TrackedEmailMessage implements QueuedMessageInterface
{
    public function __construct(
        private Email $email,
        private ?string $transportName = null,
        private array $metadata = [],
        private int $priority = 0
    ) {}
    
    public function getOriginalMessage(): Email
    {
        return $this->email;
    }
    
    public function getMessageType(): string
    {
        return 'email';
    }
    
    public function getTrackingData(): array
    {
        return [
            'subject' => $this->email->getSubject(),
            'to' => array_map(fn($addr) => $addr->toString(), $this->email->getTo()),
            'from' => $this->email->getFrom()[0]?->toString(),
            'transport' => $this->transportName,
            'priority' => $this->priority,
            'metadata' => $this->metadata,
        ];
    }
}
```

### 5. Enhanced Analytics Integration

#### Queue Analytics Provider
```php
class QueueAnalyticsProvider
{
    public function getQueueMetrics(array $filters = []): array
    {
        return [
            'queueDepth' => $this->getQueueDepth(),
            'processingRate' => $this->getProcessingRate(),
            'averageWaitTime' => $this->getAverageWaitTime(),
            'failureRate' => $this->getFailureRate(),
            'throughputMetrics' => $this->getThroughputMetrics(),
            'workerStatus' => $this->getWorkerStatus(),
            'transportBreakdown' => $this->getTransportBreakdown(),
            'priorityDistribution' => $this->getPriorityDistribution(),
        ];
    }
    
    public function getDetailedQueueAnalysis(): array
    {
        return [
            'messageFlow' => $this->getMessageFlowMetrics(),
            'bottleneckAnalysis' => $this->identifyBottlenecks(),
            'retryPatterns' => $this->analyzeRetryPatterns(),
            'transportPerformance' => $this->analyzeTransportPerformance(),
            'timeBasedMetrics' => $this->getTimeBasedMetrics(),
        ];
    }
}
```

## 🚀 Implementation Benefits

### 1. **Enhanced Control**
- **Message Prioritization**: High-priority messages processed first
- **Rate Limiting**: Control message throughput per transport
- **Retry Logic**: Sophisticated retry strategies with exponential backoff
- **Dead Letter Queue**: Handle permanently failed messages
- **Circuit Breaker**: Protect against transport failures

### 2. **Rich Analytics**
- **Queue Depth Monitoring**: Real-time queue size tracking
- **Processing Time Metrics**: Detailed timing analysis
- **Throughput Analysis**: Messages per second/minute/hour
- **Failure Pattern Detection**: Identify problematic transports/messages
- **Performance Trends**: Historical performance tracking
- **Resource Utilization**: Worker and memory usage monitoring

### 3. **Operational Excellence**
- **Health Checks**: Transport and queue health monitoring
- **Alerting**: Proactive notifications for issues
- **Scaling Insights**: Data-driven scaling decisions
- **Debugging Tools**: Detailed message tracking and logging
- **Performance Optimization**: Bottleneck identification and resolution

### 4. **Seamless Integration**
- **Backwards Compatible**: Works with existing mailer/notifier usage
- **Transparent Tracking**: Automatic message tracking without code changes
- **Event Integration**: Leverages existing event subscribers
- **Entity Relationships**: Links queued messages to tracked messages

## 📊 New Analytics Endpoints

### Enhanced Queue Analytics
```
GET /api/queue/analytics
- Queue depth over time
- Processing rate trends
- Worker utilization
- Transport performance comparison

GET /api/queue/bottlenecks
- Identify processing bottlenecks
- Suggest optimization strategies
- Resource usage analysis

GET /api/queue/failures
- Failure pattern analysis
- Retry success rates
- Dead letter queue management
```

### Message Flow Analytics
```
GET /api/analytics/message-flow
- End-to-end message journey
- Processing time breakdown
- Queue wait times
- Transport delivery times

GET /api/analytics/transport-performance
- Per-transport success rates
- Latency comparisons
- Reliability scoring
- Cost per message analysis
```

## 🔧 Configuration Options

### Transport Configuration
```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            notification_tracker:
                dsn: 'notification-tracker://default'
                options:
                    # Storage backend
                    storage: 'doctrine' # or 'redis'
                    
                    # Performance settings
                    batch_size: 10
                    prefetch_count: 5
                    
                    # Retry configuration
                    max_retries: 3
                    retry_delay: 1000 # milliseconds
                    retry_multiplier: 2
                    max_retry_delay: 60000
                    
                    # Priority settings
                    priority_levels: 5
                    high_priority_threshold: 100
                    
                    # Rate limiting
                    rate_limit: 100 # messages per minute
                    burst_limit: 20
                    
                    # Monitoring
                    enable_metrics: true
                    metrics_interval: 60 # seconds
                    
                    # Dead letter queue
                    dlq_enabled: true
                    dlq_max_age: 86400 # seconds
```

### Bundle Configuration
```yaml
# config/packages/notification_tracker.yaml
notification_tracker:
    messenger:
        enabled: true
        transport: 'notification_tracker'
        
        # Auto-route messages to our transport
        auto_route:
            email: true
            notifier: true
            
        # Queue settings
        queue:
            default_priority: 0
            high_priority_types: ['alert', 'critical']
            
        # Analytics
        analytics:
            queue_metrics: true
            detailed_tracking: true
            performance_monitoring: true
```

## 📈 Implementation Phases

### Phase 1: Core Transport (Week 1)
- [ ] Basic transport implementation
- [ ] QueuedMessage entity
- [ ] Simple doctrine storage
- [ ] Basic message handling

### Phase 2: Enhanced Features (Week 2)
- [ ] Priority queuing
- [ ] Retry logic
- [ ] Message serialization
- [ ] Transport factory

### Phase 3: Analytics Integration (Week 3)
- [ ] Queue metrics collection
- [ ] Performance tracking
- [ ] Enhanced analytics endpoints
- [ ] Monitoring tools

### Phase 4: Advanced Features (Week 4)
- [ ] Redis storage option
- [ ] Circuit breaker pattern
- [ ] Dead letter queue
- [ ] Advanced monitoring

### Phase 5: Production Optimization (Week 5)
- [ ] Performance optimization
- [ ] Load testing
- [ ] Documentation
- [ ] Migration tools

## 🎯 Success Metrics

- **Queue Processing**: 1000+ messages/minute throughput capability
- **Low Latency**: <100ms average queue processing time
- **High Reliability**: 99.9% message delivery success rate
- **Rich Analytics**: 20+ new queue and transport metrics
- **Operational Excellence**: Real-time monitoring and alerting
- **Seamless Migration**: Zero-downtime transition from existing setup

This custom transport will transform the notification tracking system into a true enterprise-grade message processing platform with unparalleled visibility and control over message delivery.
