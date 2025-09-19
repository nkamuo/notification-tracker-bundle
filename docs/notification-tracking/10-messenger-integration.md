# Messenger Component Integration

## Message Handlers for Async Processing

```php
<?php
// src/MessageHandler/TrackEmailMessageHandler.php

namespace App\MessageHandler;

use App\Entity\Communication\MessageEvent;
use App\Service\Communication\MessageTracker;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Mime\Email;

#[AsMessageHandler(priority: 100)]
class TrackEmailMessageHandler
{
    public function __construct(
        private MessageTracker $messageTracker,
        private LoggerInterface $logger
    ) {}

    public function __invoke(SendEmailMessage $message): void
    {
        $email = $message->getMessage();
        
        if (!$email instanceof Email) {
            return;
        }

        try {
            // Check if already tracked
            $trackingId = $email->getHeaders()->get('X-Tracking-ID')?->getBody();
            
            if (!$trackingId) {
                // Create tracking entry for async email
                $trackedMessage = $this->messageTracker->trackEmail(
                    $email,
                    $message->getTransport() ?? 'async',
                    null,
                    [
                        'async' => true,
                        'envelope' => [
                            'sender' => $message->getEnvelope()?->getSender()?->toString(),
                            'recipients' => array_map(
                                fn($r) => $r->toString(),
                                $message->getEnvelope()?->getRecipients() ?? []
                            ),
                        ],
                    ]
                );

                // Add tracking ID to email
                $email->getHeaders()->addTextHeader('X-Tracking-ID', (string) $trackedMessage->getId());

                $this->logger->info('Email tracked in async handler', [
                    'tracking_id' => $trackedMessage->getId(),
                    'subject' => $email->getSubject(),
                ]);
            }

        } catch (\Exception $e) {
            $this->logger->error('Failed to track email in async handler', [
                'error' => $e->getMessage(),
                'subject' => $email->getSubject(),
            ]);
            
            // Don't fail the message, just log the error
            // The email should still be sent even if tracking fails
        }
    }
}
```

```php
<?php
// src/MessageHandler/ProcessWebhookMessageHandler.php

namespace App\MessageHandler;

use App\Message\ProcessWebhookMessage;
use App\Service\Communication\WebhookProcessor;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
class ProcessWebhookMessageHandler
{
    public function __construct(
        private WebhookProcessor $webhookProcessor,
        private LoggerInterface $logger
    ) {}

    public function __invoke(ProcessWebhookMessage $message): void
    {
        try {
            $this->webhookProcessor->processWebhook(
                $message->getProvider(),
                $message->getPayload(),
                $message->getHeaders()
            );

            $this->logger->info('Webhook processed successfully', [
                'provider' => $message->getProvider(),
                'webhook_id' => $message->getWebhookId(),
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to process webhook', [
                'provider' => $message->getProvider(),
                'error' => $e->getMessage(),
            ]);

            // Retry for recoverable errors
            if ($this->isRecoverableError($e)) {
                throw new RecoverableMessageHandlingException(
                    'Webhook processing failed, will retry',
                    0,
                    $e
                );
            }

            // Don't retry for permanent failures
            throw new UnrecoverableMessageHandlingException(
                'Webhook processing permanently failed',
                0,
                $e
            );
        }
    }

    private function isRecoverableError(\Exception $e): bool
    {
        // Define which errors are recoverable
        return !($e instanceof \InvalidArgumentException);
    }
}
```

```php
<?php
// src/Message/ProcessWebhookMessage.php

namespace App\Message;

use Symfony\Component\Uid\Uuid;

class ProcessWebhookMessage
{
    public function __construct(
        private string $provider,
        private array $payload,
        private array $headers = [],
        private ?Uuid $webhookId = null
    ) {
        $this->webhookId = $webhookId ?? Uuid::v7();
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getWebhookId(): Uuid
    {
        return $this->webhookId;
    }
}
```

## Messenger Middleware for Tracking

```php
<?php
// src/Messenger/Middleware/MessageTrackingMiddleware.php

namespace App\Messenger\Middleware;

use App\Entity\Communication\MessageEvent;
use App\Service\Communication\MessageTracker;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Mime\Email;

class MessageTrackingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private MessageTracker $messageTracker,
        private LoggerInterface $logger
    ) {}

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();

        // Track email messages
        if ($message instanceof SendEmailMessage) {
            $this->trackEmailEnvelope($envelope, $message);
        }

        try {
            // Process the message
            $envelope = $stack->next()->handle($envelope, $stack);

            // Track successful processing
            $this->trackSuccess($envelope);

        } catch (\Throwable $e) {
            // Track failure
            $this->trackFailure($envelope, $e);
            
            throw $e;
        }

        return $envelope;
    }

    private function trackEmailEnvelope(Envelope $envelope, SendEmailMessage $message): void
    {
        $email = $message->getMessage();
        
        if (!$email instanceof Email) {
            return;
        }

        // Check for redelivery
        $redeliveryStamp = $envelope->last(RedeliveryStamp::class);
        if ($redeliveryStamp) {
            $this->trackRedelivery($email, $redeliveryStamp);
        }

        // Check if sent to failure transport
        $failureStamp = $envelope->last(SentToFailureTransportStamp::class);
        if ($failureStamp) {
            $this->trackSentToFailure($email, $failureStamp);
        }
    }

    private function trackRedelivery(Email $email, RedeliveryStamp $stamp): void
    {
        $trackingId = $email->getHeaders()->get('X-Tracking-ID')?->getBody();
        
        if (!$trackingId) {
            return;
        }

        try {
            $trackedMessage = $this->messageTracker->findById($trackingId);
            
            if ($trackedMessage) {
                $this->messageTracker->addEvent(
                    $trackedMessage,
                    MessageEvent::TYPE_RETRIED,
                    [
                        'retry_count' => $stamp->getRetryCount(),
                        'redelivered_at' => $stamp->getRedeliveredAt()->format('c'),
                        'error_message' => $stamp->getExceptionMessage(),
                    ]
                );

                $trackedMessage->setRetryCount($stamp->getRetryCount());
            }

        } catch (\Exception $e) {
            $this->logger->error('Failed to track redelivery', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function trackSentToFailure(Email $email, SentToFailureTransportStamp $stamp): void
    {
        $trackingId = $email->getHeaders()->get('X-Tracking-ID')?->getBody();
        
        if (!$trackingId) {
            return;
        }

        try {
            $trackedMessage = $this->messageTracker->findById($trackingId);
            
            if ($trackedMessage) {
                $this->messageTracker->addEvent(
                    $trackedMessage,
                    MessageEvent::TYPE_FAILED,
                    [
                        'sent_to_failure_transport' => true,
                        'original_message_class' => $stamp->getOriginalMessageClass(),
                    ]
                );

                $trackedMessage->setStatus(Message::STATUS_FAILED);
            }

        } catch (\Exception $e) {
            $this->logger->error('Failed to track failure transport', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function trackSuccess(Envelope $envelope): void
    {
        // Implementation for tracking successful processing
    }

    private function trackFailure(Envelope $envelope, \Throwable $error): void
    {
        // Get error details stamp
        $errorStamp = ErrorDetailsStamp::create($error);
        
        $message = $envelope->getMessage();
        
        if ($message instanceof SendEmailMessage) {
            $email = $message->getMessage();
            
            if ($email instanceof Email) {
                $trackingId = $email->getHeaders()->get('X-Tracking-ID')?->getBody();
                
                if ($trackingId) {
                    try {
                        $trackedMessage = $this->messageTracker->findById($trackingId);
                        
                        if ($trackedMessage) {
                            $this->messageTracker->addEvent(
                                $trackedMessage,
                                MessageEvent::TYPE_FAILED,
                                [
                                    'error_class' => $errorStamp->getExceptionClass(),
                                    'error_message' => $errorStamp->getExceptionMessage(),
                                    'error_code' => $errorStamp->getExceptionCode(),
                                ]
                            );
                        }
                        
                    } catch (\Exception $e) {
                        $this->logger->error('Failed to track message failure', [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }
    }
}
```