<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Service;

use Nkamuo\NotificationTrackerBundle\Webhook\Provider\WebhookProviderInterface;

/**
 * Registry for webhook providers
 */
class WebhookProviderRegistry
{
    /** @var WebhookProviderInterface[] */
    private array $providers = [];

    public function addProvider(WebhookProviderInterface $provider): void
    {
        $this->providers[$provider->getProviderName()] = $provider;
    }

    public function getProvider(string $providerName): ?WebhookProviderInterface
    {
        return $this->providers[$providerName] ?? null;
    }

    public function hasProvider(string $providerName): bool
    {
        return isset($this->providers[$providerName]);
    }

    /**
     * @return WebhookProviderInterface[]
     */
    public function getAllProviders(): array
    {
        return $this->providers;
    }

    /**
     * @return string[]
     */
    public function getAvailableProviders(): array
    {
        return array_keys($this->providers);
    }

    /**
     * Get configuration schema for all providers
     * 
     * @return array<string, array<string, array>>
     */
    public function getProvidersConfigurationSchema(): array
    {
        $schema = [];
        
        foreach ($this->providers as $providerName => $provider) {
            $schema[$providerName] = [
                'name' => $providerName,
                'fields' => $provider->getConfigurationFields(),
            ];
        }

        return $schema;
    }

    /**
     * Validate configuration for a specific provider
     */
    public function validateProviderConfiguration(string $providerName, array $config): array
    {
        $provider = $this->getProvider($providerName);
        
        if (!$provider) {
            return ['provider' => 'Provider not found'];
        }

        return $provider->validateConfiguration($config);
    }
}
