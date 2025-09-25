<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\ApiPlatform\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

/**
 * Filter to exclude records where a property value equals a given value.
 * 
 * ⚠️ EXPERIMENTAL - This filter is in development and may change without notice.
 * 
 * Usage: ?property[ne]=value
 * Example: ?status[ne]=pending
 * 
 * @experimental
 */
final class NotEqualsFilter extends AbstractFilter
{
    public const NOT_EQUALS_STRATEGY = 'ne';

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

        $strategy = $value[self::NOT_EQUALS_STRATEGY] ?? null;
        if (null === $strategy || '' === $strategy) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $field = $this->getNestedFieldName($property, $alias);
        
        $parameterName = $queryNameGenerator->generateParameterName($property);
        
        // Handle null values separately
        if ('null' === strtolower((string) $strategy)) {
            $queryBuilder->andWhere(sprintf('%s IS NOT NULL', $field));
        } else {
            $queryBuilder
                ->andWhere(sprintf('%s != :%s OR %s IS NULL', $field, $parameterName, $field))
                ->setParameter($parameterName, $strategy);
        }
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
            $description["{$propertyName}[" . self::NOT_EQUALS_STRATEGY . "]"] = [
                'property' => $property,
                'type' => 'string',
                'required' => false,
                'description' => sprintf(
                    'Filter by %s not equal to the given value. Use "null" to exclude null values.',
                    $propertyName
                ),
                'openapi' => [
                    'example' => 'value_to_exclude',
                ],
                'schema' => [
                    'type' => 'string',
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