<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Nkamuo\NotificationTrackerBundle\Entity\Label;
use Nkamuo\NotificationTrackerBundle\Entity\Message;
use Nkamuo\NotificationTrackerBundle\Entity\Notification;
use Nkamuo\NotificationTrackerBundle\Event\InboundMessageEvent;
use Nkamuo\NotificationTrackerBundle\Event\MessageCreatedEvent;
use Nkamuo\NotificationTrackerBundle\Event\MessagePostProcessEvent;
use Nkamuo\NotificationTrackerBundle\Event\MessagePreProcessEvent;
use Nkamuo\NotificationTrackerBundle\Event\NotificationCreatedEvent;
use Nkamuo\NotificationTrackerBundle\Event\NotificationPostSendEvent;
use Nkamuo\NotificationTrackerBundle\Event\NotificationPreSendEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Service for enriching notifications and messages through events
 */
class EventEnrichmentService
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Enrich a notification at creation time
     */
    public function enrichNotificationOnCreation(Notification $notification, array $context = []): bool
    {
        $event = new NotificationCreatedEvent($notification, $context);
        $this->eventDispatcher->dispatch($event, NotificationCreatedEvent::NAME);
        
        return !$event->shouldStopProcessing();
    }

    /**
     * Enrich a notification before sending
     */
    public function enrichNotificationPreSend(Notification $notification, array $context = []): bool
    {
        $event = new NotificationPreSendEvent($notification, $context);
        $this->eventDispatcher->dispatch($event, NotificationPreSendEvent::NAME);
        
        return !$event->shouldCancelSending();
    }

    /**
     * Process post-send notification events
     */
    public function processNotificationPostSend(
        Notification $notification, 
        bool $success, 
        ?string $errorMessage = null, 
        array $context = []
    ): void {
        $event = new NotificationPostSendEvent($notification, $success, $errorMessage, $context);
        $this->eventDispatcher->dispatch($event, NotificationPostSendEvent::NAME);
    }

    /**
     * Enrich a message at creation time
     */
    public function enrichMessageOnCreation(Message $message, array $context = []): bool
    {
        $event = new MessageCreatedEvent($message, $context);
        $this->eventDispatcher->dispatch($event, MessageCreatedEvent::NAME);
        
        return !$event->shouldStopProcessing();
    }

    /**
     * Enrich a message before processing
     */
    public function enrichMessagePreProcess(Message $message, array $context = []): bool
    {
        $event = new MessagePreProcessEvent($message, $context);
        $this->eventDispatcher->dispatch($event, MessagePreProcessEvent::NAME);
        
        return !$event->shouldCancelProcessing();
    }

    /**
     * Process post-processing message events
     */
    public function processMessagePostProcess(
        Message $message, 
        bool $success, 
        ?string $errorMessage = null, 
        array $context = []
    ): void {
        $event = new MessagePostProcessEvent($message, $success, $errorMessage, $context);
        $this->eventDispatcher->dispatch($event, MessagePostProcessEvent::NAME);
    }

    /**
     * Process inbound message events
     */
    public function processInboundMessage(
        Message $message, 
        array $rawData, 
        string $provider, 
        array $context = []
    ): void {
        $event = new InboundMessageEvent($message, $rawData, $provider, $context);
        $this->eventDispatcher->dispatch($event, InboundMessageEvent::NAME);
    }

    /**
     * Utility method to add labels to an entity (Notification or Message)
     */
    public function addLabel(object $entity, string $labelName, ?string $description = null): void
    {
        if (!($entity instanceof Notification) && !($entity instanceof Message)) {
            throw new \InvalidArgumentException('Entity must be Notification or Message');
        }

        // Check if label already exists on the entity
        foreach ($entity->getLabels() as $existingLabel) {
            if ($existingLabel->getName() === $labelName) {
                return; // Label already exists
            }
        }

        // Find or create label
        $label = $this->entityManager->getRepository(Label::class)->findOneBy(['name' => $labelName]);
        if (!$label) {
            $label = new Label();
            $label->setName($labelName);
            if ($description !== null) {
                $label->setDescription($description);
            }
            $this->entityManager->persist($label);
        }

        // Add label to entity
        $entity->addLabel($label);
        
        // Also add entity to label (bidirectional relationship)
        if ($entity instanceof Notification) {
            $label->addNotification($entity);
        } else {
            $label->addMessage($entity);
        }
    }

    /**
     * Utility method to add metadata to an entity
     */
    public function addMetadata(object $entity, string $key, mixed $value): void
    {
        if (!($entity instanceof Notification) && !($entity instanceof Message)) {
            throw new \InvalidArgumentException('Entity must be Notification or Message');
        }

        $metadata = $entity->getMetadata() ?? [];
        $metadata[$key] = $value;
        $entity->setMetadata($metadata);
    }
}
