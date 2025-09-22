<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use Nkamuo\NotificationTrackerBundle\Controller\Api\SendNotificationController;

#[ApiResource(
    shortName: 'SendNotification',
    operations: [
        new Post(
            uriTemplate: '/send/notification',
            controller: SendNotificationController::class,
            name: 'send_notification',
            description: 'Send a notification to multiple channels (email, SMS, Slack) simultaneously'
        )
    ]
)]
class SendNotificationResource
{
    // This is a placeholder resource for API Platform
    // The actual logic is in SendNotificationController
}
