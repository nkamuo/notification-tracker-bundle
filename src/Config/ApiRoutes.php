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
     * Individual resource prefixes (relative to BASE_PREFIX)
     */
    public const NOTIFICATIONS = self::BASE_PREFIX . '/notifications';
    public const MESSAGES = self::BASE_PREFIX . '/messages';
    public const TEMPLATES = self::BASE_PREFIX . '/templates';
    public const EVENTS = self::BASE_PREFIX . '/events';
    public const RECIPIENTS = self::BASE_PREFIX . '/recipients';
    public const WEBHOOKS = self::BASE_PREFIX . '/webhooks';
    public const STATISTICS = self::BASE_PREFIX . '/statistics';
    public const ATTACHMENTS = self::BASE_PREFIX . '/attachments';

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
