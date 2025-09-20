<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Transport;

use Doctrine\ORM\EntityManagerInterface;
use Nkamuo\NotificationTrackerBundle\Repository\QueuedMessageRepository;
use Nkamuo\NotificationTrackerBundle\Service\NotificationAnalyticsCollector;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

class NotificationTrackingTransportFactory implements TransportFactoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private QueuedMessageRepository $repository,
        private NotificationAnalyticsCollector $analyticsCollector
    ) {}

    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        $parsedOptions = $this->parseDsn($dsn, $options);

        return new NotificationTrackingTransport(
            $this->entityManager,
            $this->repository,
            $serializer,
            $this->analyticsCollector,
            $parsedOptions
        );
    }

    public function supports(string $dsn, array $options): bool
    {
        return str_starts_with($dsn, 'notification-tracking://');
    }

    private function parseDsn(string $dsn, array $options): array
    {
        // Parse the DSN: notification-tracking://doctrine?transport_name=email&queue_name=default&analytics_enabled=true
        $parsed = parse_url($dsn);
        
        if ($parsed === false || !isset($parsed['scheme']) || $parsed['scheme'] !== 'notification-tracking') {
            throw new InvalidArgumentException(sprintf('Invalid DSN "%s". Expected format: notification-tracking://doctrine?options', $dsn));
        }

        // Validate host
        if (!isset($parsed['host']) || $parsed['host'] !== 'doctrine') {
            throw new InvalidArgumentException(sprintf('Invalid DSN "%s". Expected format: notification-tracking://doctrine?options', $dsn));
        }

        // Parse query parameters
        $queryOptions = [];
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $queryOptions);
        }

        // Merge with explicit options (explicit options take precedence)
        $parsedOptions = array_merge($queryOptions, $options);

        // Set defaults and validate/convert types
        $parsedOptions = $this->setDefaults($parsedOptions);
        $parsedOptions = $this->validateOptions($parsedOptions);

        return $parsedOptions;
    }

    private function setDefaults(array $options): array
    {
        return array_merge([
            'transport_name' => 'default',
            'queue_name' => 'default',
            'analytics_enabled' => 'true',
            'provider_aware_routing' => 'false',
            'batch_size' => '10',
            'max_retries' => '3',
            'retry_delays' => '1000,5000,30000', // milliseconds, comma-separated
        ], $options);
    }

    private function validateOptions(array $options): array
    {
        // Convert string booleans to actual booleans
        $booleanOptions = ['analytics_enabled', 'provider_aware_routing'];
        foreach ($booleanOptions as $key) {
            if (isset($options[$key])) {
                if (is_string($options[$key])) {
                    $value = strtolower(trim($options[$key]));
                    if (in_array($value, ['true', '1', 'yes', 'on'], true)) {
                        $options[$key] = true;
                    } elseif (in_array($value, ['false', '0', 'no', 'off'], true)) {
                        $options[$key] = false;
                    } else {
                        throw new InvalidArgumentException(sprintf('Option "%s" must be a boolean, got "%s".', $key, $options[$key]));
                    }
                }
            }
        }

        // Convert string integers to actual integers
        $integerOptions = ['batch_size', 'max_retries'];
        foreach ($integerOptions as $key) {
            if (isset($options[$key])) {
                $value = filter_var($options[$key], FILTER_VALIDATE_INT);
                if ($value === false) {
                    throw new InvalidArgumentException(sprintf('Option "%s" must be an integer, got "%s".', $key, $options[$key]));
                }
                $options[$key] = $value;
            }
        }

        // Parse retry delays
        if (isset($options['retry_delays'])) {
            if (is_string($options['retry_delays'])) {
                $delays = explode(',', $options['retry_delays']);
                $options['retry_delays'] = array_map(function ($delay) {
                    $trimmed = trim($delay);
                    $value = filter_var($trimmed, FILTER_VALIDATE_INT);
                    if ($value === false) {
                        throw new InvalidArgumentException(sprintf('Invalid retry delay "%s". All delays must be positive integers.', $trimmed));
                    }
                    if ($value <= 0) {
                        throw new InvalidArgumentException(sprintf('Invalid retry delay "%s". All delays must be positive integers.', $trimmed));
                    }
                    return $value;
                }, $delays);
            }
        }

        // Validate string lengths first
        if (empty($options['transport_name'])) {
            throw new InvalidArgumentException('Transport name cannot be empty.');
        }
        if (empty($options['queue_name'])) {
            throw new InvalidArgumentException('Queue name cannot be empty.');
        }
        if (strlen($options['transport_name']) > 100) {
            throw new InvalidArgumentException(sprintf('Transport name must be 100 characters or less, got %d characters.', strlen($options['transport_name'])));
        }
        if (strlen($options['queue_name']) > 100) {
            throw new InvalidArgumentException(sprintf('Queue name must be 100 characters or less, got %d characters.', strlen($options['queue_name'])));
        }

        // Validate transport and queue names
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $options['transport_name'])) {
            throw new InvalidArgumentException(sprintf('Transport name "%s" contains invalid characters. Only alphanumeric, underscore, and hyphen allowed.', $options['transport_name']));
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $options['queue_name'])) {
            throw new InvalidArgumentException(sprintf('Queue name "%s" contains invalid characters. Only alphanumeric, underscore, and hyphen allowed.', $options['queue_name']));
        }

        // Validate batch size
        if ($options['batch_size'] < 1 || $options['batch_size'] > 100) {
            throw new InvalidArgumentException(sprintf('Batch size must be between 1 and 100, got %d.', $options['batch_size']));
        }

        // Validate max retries
        if ($options['max_retries'] < 0 || $options['max_retries'] > 10) {
            throw new InvalidArgumentException(sprintf('Max retries must be between 0 and 10, got %d.', $options['max_retries']));
        }

        return $options;
    }
}
