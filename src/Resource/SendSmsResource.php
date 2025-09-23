<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use Nkamuo\NotificationTrackerBundle\Controller\Api\SendSmsController;

#[ApiResource(
    shortName: 'SendSms',
    operations: [
        new Post(
            uriTemplate: '/send/sms',
            controller: SendSmsController::class,
            name: 'send_sms',
            description: 'Send an SMS immediately or save as draft/schedule'
        )
    ]
)]
class SendSmsResource
{
    // This is a placeholder resource for API Platform
    // The actual logic is in SendSmsController
}
