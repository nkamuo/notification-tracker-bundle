<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Tests\Unit\Filter;

use PHPUnit\Framework\TestCase;
use Nkamuo\NotificationTrackerBundle\ApiPlatform\Filter\NotInFilter;
use Nkamuo\NotificationTrackerBundle\ApiPlatform\Filter\NotEqualsFilter;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\EntityManagerInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;

class CustomFiltersTest extends TestCase
{
    public function testNotInFilterDescription(): void
    {
        $filter = new NotInFilter();
        
        // Set properties manually since we're testing in isolation
        $reflection = new \ReflectionClass($filter);
        $propertiesProperty = $reflection->getProperty('properties');
        $propertiesProperty->setAccessible(true);
        $propertiesProperty->setValue($filter, [
            'status' => null,
            'type' => null,
        ]);

        $description = $filter->getDescription('TestClass');

        $this->assertArrayHasKey('status[notin]', $description);
        $this->assertArrayHasKey('type[notin]', $description);

        $statusFilter = $description['status[notin]'];
        $this->assertEquals('status', $statusFilter['property']);
        $this->assertEquals('array', $statusFilter['type']);
        $this->assertFalse($statusFilter['required']);
        $this->assertStringContainsString('not in the given values', $statusFilter['description']);
        $this->assertEquals('value1,value2,value3', $statusFilter['openapi']['example']);
    }

    public function testNotEqualsFilterDescription(): void
    {
        $filter = new NotEqualsFilter();
        
        // Set properties manually since we're testing in isolation
        $reflection = new \ReflectionClass($filter);
        $propertiesProperty = $reflection->getProperty('properties');
        $propertiesProperty->setAccessible(true);
        $propertiesProperty->setValue($filter, [
            'status' => null,
            'type' => null,
        ]);

        $description = $filter->getDescription('TestClass');

        $this->assertArrayHasKey('status[ne]', $description);
        $this->assertArrayHasKey('type[ne]', $description);

        $statusFilter = $description['status[ne]'];
        $this->assertEquals('status', $statusFilter['property']);
        $this->assertEquals('string', $statusFilter['type']);
        $this->assertFalse($statusFilter['required']);
        $this->assertStringContainsString('not equal to the given value', $statusFilter['description']);
        $this->assertEquals('value_to_exclude', $statusFilter['openapi']['example']);
    }

    public function testNotInFilterConstants(): void
    {
        $this->assertEquals('notin', NotInFilter::NOT_IN_STRATEGY);
    }

    public function testNotEqualsFilterConstants(): void
    {
        $this->assertEquals('ne', NotEqualsFilter::NOT_EQUALS_STRATEGY);
    }

    public function testNotInFilterWithNestedProperty(): void
    {
        $filter = new NotInFilter();
        
        // Set properties with nested property
        $reflection = new \ReflectionClass($filter);
        $propertiesProperty = $reflection->getProperty('properties');
        $propertiesProperty->setAccessible(true);
        $propertiesProperty->setValue($filter, [
            'notification.status' => null,
        ]);

        $description = $filter->getDescription('TestClass');

        $this->assertArrayHasKey('notification_status[notin]', $description);
        
        $filter_desc = $description['notification_status[notin]'];
        $this->assertEquals('notification.status', $filter_desc['property']);
    }

    public function testNotEqualsFilterWithNestedProperty(): void
    {
        $filter = new NotEqualsFilter();
        
        // Set properties with nested property
        $reflection = new \ReflectionClass($filter);
        $propertiesProperty = $reflection->getProperty('properties');
        $propertiesProperty->setAccessible(true);
        $propertiesProperty->setValue($filter, [
            'notification.status' => null,
        ]);

        $description = $filter->getDescription('TestClass');

        $this->assertArrayHasKey('notification_status[ne]', $description);
        
        $filter_desc = $description['notification_status[ne]'];
        $this->assertEquals('notification.status', $filter_desc['property']);
    }
}
