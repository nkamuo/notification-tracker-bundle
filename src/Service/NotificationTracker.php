<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Nkamuo\NotificationTrackerBundle\Entity\Notification;
use Psr\Log\LoggerInterface;
use Symfony\Component\Notifier\Notification\Notification as SymfonyNotification;

class NotificationTracker
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function createFromSymfonyNotification(SymfonyNotification $symfonyNotification): Notification
    {
        $notification = new Notification();
        $notification->setType('symfony_notification');
        $notification->setSubject($symfonyNotification->getSubject());
        $notification->setImportance($symfonyNotification->getImportance());
        
        $channels = [];
        foreach ($symfonyNotification->getChannels() as $channel) {
            $channels[] = $channel;
        }
        $notification->setChannels($channels);
        
        $this->entityManager->persist($notification);
        $this->entityManager->flush();
        
        return $notification;
    }
}