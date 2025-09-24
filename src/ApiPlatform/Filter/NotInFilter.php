<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\ApiPlatform\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

/**
 * Filter to exclude records where a property value is in a given set of values.
 * 
 * Usage: ?property[notin]=value1,value2,value3
 * Example: ?status[notin]=pending,failed
 */
final class NotInFilter extends AbstractFilter
{
    public const NOT_IN_STRATEGY = 'notin';

    protected function filterProperty(
        string $property,
        mixed $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []
    ): void {
        if (
            !$this->isPropertyEnabled($property, $resourceClass) ||
            !$this->isPropertyMapped($property, $resourceClass)
        ) {
            return;
        }

        if (!is_array($value)) {
            return;
        }

        $strategy = $value[self::NOT_IN_STRATEGY] ?? null;
        if (null === $strategy) {
            return;
        }

        // Handle comma-separated values
        if (is_string($strategy)) {
            $values = array_map('trim', explode(',', $strategy));
        } else {
            $values = (array) $strategy;
        }

        // Filter out empty values
        $values = array_filter($values, fn($val) => !empty($val));

        if (empty($values)) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $field = $this->getNestedFieldName($property, $alias);
        
        $parameterName = $queryNameGenerator->generateParameterName($property);
        
        $queryBuilder
            ->andWhere(sprintf('%s NOT IN (:%s)', $field, $parameterName))
            ->setParameter($parameterName, $values);
    }

    public function getDescription(string $resourceClass): array
    {
        if (!$this->properties) {
            return [];
        }

        $description = [];
        foreach ($this->properties as $property => $strategy) {
            if (!$this->isPropertyMapped($property, $resourceClass)) {
                continue;
            }

            $propertyName = $this->normalizePropertyNameForDescription($property);
            $description["{$propertyName}[" . self::NOT_IN_STRATEGY . "]"] = [
                'property' => $property,
                'type' => 'array',
                'required' => false,
                'description' => sprintf(
                    'Filter by %s not in the given values. Accepts comma-separated values.',
                    $propertyName
                ),
                'openapi' => [
                    'example' => 'value1,value2,value3',
                    'explode' => false,
                    'style' => 'simple',
                ],
                'schema' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                    ],
                ],
            ];
        }

        return $description;
    }

    /**
     * Handle nested properties like 'notification.status'
     */
    private function getNestedFieldName(string $property, string $alias): string
    {
        if (!str_contains($property, '.')) {
            return sprintf('%s.%s', $alias, $property);
        }

        $parts = explode('.', $property);
        $field = array_pop($parts);
        $currentAlias = $alias;
        
        foreach ($parts as $part) {
            $joinAlias = $currentAlias . '_' . $part;
            $currentAlias = $joinAlias;
        }
        
        return sprintf('%s.%s', $currentAlias, $field);
    }

    /**
     * Normalize property name for description
     */
    private function normalizePropertyNameForDescription(string $property): string
    {
        return str_replace('.', '_', $property);
    }
}