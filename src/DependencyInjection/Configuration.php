<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('notification_tracker');
        
        $treeBuilder->getRootNode()
            ->children()
                ->booleanNode('enabled')
                    ->defaultTrue()
                ->end()
                
                ->arrayNode('tracking')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->booleanNode('track_opens')->defaultTrue()->end()
                        ->booleanNode('track_clicks')->defaultTrue()->end()
                        ->booleanNode('store_content')->defaultTrue()->end()
                        ->integerNode('content_retention_days')->defaultValue(90)->end()
                        ->booleanNode('use_async')->defaultTrue()->end()
                    ->end()
                ->end()
                
                ->arrayNode('storage')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('attachment_directory')
                            ->defaultValue('%kernel.project_dir%/var/notification-tracker/attachments')
                        ->end()
                        ->scalarNode('template_directory')
                            ->defaultValue('%kernel.project_dir%/var/notification-tracker/templates')
                        ->end()
                        ->integerNode('max_attachment_size')
                            ->defaultValue(10485760)
                        ->end()
                        ->arrayNode('allowed_mime_types')
                            ->scalarPrototype()->end()
                            ->defaultValue(['application/pdf', 'image/jpeg', 'image/png'])
                        ->end()
                    ->end()
                ->end()
                
                ->arrayNode('retry')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('max_retries')->defaultValue(3)->end()
                        ->integerNode('retry_delay')->defaultValue(3600)->end()
                        ->floatNode('retry_multiplier')->defaultValue(2.0)->end()
                        ->integerNode('max_retry_delay')->defaultValue(86400)->end()
                    ->end()
                ->end()
                
                ->arrayNode('webhooks')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->booleanNode('async_processing')->defaultTrue()->end()
                        ->booleanNode('verify_signatures')->defaultTrue()->end()
                        ->booleanNode('ip_whitelist_enabled')->defaultFalse()->end()
                        ->arrayNode('providers')
                            ->useAttributeAsKey('name')
                            ->arrayPrototype()
                                ->children()
                                    ->scalarNode('secret')->end()
                                    ->scalarNode('signing_key')->end()
                                    ->scalarNode('auth_token')->end()
                                    ->arrayNode('ip_whitelist')
                                        ->scalarPrototype()->end()
                                    ->end()
                                    ->arrayNode('events')
                                        ->scalarPrototype()->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                
                // ... rest of configuration
            ->end();
        
        return $treeBuilder;
    }
}