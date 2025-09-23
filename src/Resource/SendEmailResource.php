<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use Nkamuo\NotificationTrackerBundle\Controller\Api\SendEmailController;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'SendEmailMessage',
    operations: [
        new Post(
            uriTemplate: '/send/email',
            controller: SendEmailController::class,
            name: 'send_email',
            description: 'Send an email immediately or save as draft/schedule'
        )
        ],
        routePrefix: '/notification-tracker',
)]
class SendEmailResource
{
    // This is a placeholder resource for API Platform
    // The actual logic is in SendEmailController
}
