<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Config;

/**
 * Central configuration for API route prefixes
 * Ensures consistent routing across all API resources
 */
final class ApiRoutes
{
    /**
     * Base prefix for all notification tracker API endpoints
     */
    public const BASE_PREFIX = '/notification-tracker';

    /**
     * Individual resource prefixes (can be used directly in attributes)
     */
    public const NOTIFICATIONS = '/notification-tracker/notifications';
    public const MESSAGES = '/notification-tracker/messages';
    public const TEMPLATES = '/notification-tracker/templates';
    public const EVENTS = '/notification-tracker/events';
    public const RECIPIENTS = '/notification-tracker/recipients';
    public const WEBHOOKS = '/notification-tracker/webhooks';
    public const STATISTICS = '/notification-tracker/statistics';
    public const ATTACHMENTS = '/notification-tracker/attachments';

    // >>> DIFFERENT MESSAGE TYPES

    public const EMAIL_MESSAGES = '/notification-tracker/email-messages';
    public const SMS_MESSAGES = '/notification-tracker/sms-messages';
    public const PUSH_MESSAGES = '/notification-tracker/push-messages';

    // <<< DIFFERENT MESSAGE TYPES

    /**
     * Get the full route for a specific resource endpoint
     */
    public static function getNotification(string $suffix = ''): string
    {
        return self::NOTIFICATIONS . $suffix;
    }

    public static function getMessage(string $suffix = ''): string
    {
        return self::MESSAGES . $suffix;
    }

    public static function getTemplate(string $suffix = ''): string
    {
        return self::TEMPLATES . $suffix;
    }

    public static function getEvent(string $suffix = ''): string
    {
        return self::EVENTS . $suffix;
    }

    public static function getRecipient(string $suffix = ''): string
    {
        return self::RECIPIENTS . $suffix;
    }

    public static function getWebhook(string $suffix = ''): string
    {
        return self::WEBHOOKS . $suffix;
    }

    public static function getStatistics(string $suffix = ''): string
    {
        return self::STATISTICS . $suffix;
    }

    public static function getAttachment(string $suffix = ''): string
    {
        return self::ATTACHMENTS . $suffix;
    }

    /**
     * All available route patterns for validation
     */
    public const ALL_PREFIXES = [
        'notifications' => self::NOTIFICATIONS,
        'messages' => self::MESSAGES,
        'templates' => self::TEMPLATES,
        'events' => self::EVENTS,
        'recipients' => self::RECIPIENTS,
        'webhooks' => self::WEBHOOKS,
        'statistics' => self::STATISTICS,
        'attachments' => self::ATTACHMENTS,
    ];
}
