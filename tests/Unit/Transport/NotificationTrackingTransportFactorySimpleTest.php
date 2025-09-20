<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Tests\Unit\Transport;

use Doctrine\ORM\EntityManagerInterface;
use Nkamuo\NotificationTrackerBundle\Repository\QueuedMessageRepository;
use Nkamuo\NotificationTrackerBundle\Service\NotificationAnalyticsCollector;
use Nkamuo\NotificationTrackerBundle\Transport\NotificationTrackingTransport;
use Nkamuo\NotificationTrackerBundle\Transport\NotificationTrackingTransportFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Simplified unit tests focused on core functionality
 */
class NotificationTrackingTransportFactorySimpleTest extends TestCase
{
    private NotificationTrackingTransportFactory $factory;

    protected function setUp(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(QueuedMessageRepository::class);
        $analyticsCollector = $this->createMock(NotificationAnalyticsCollector::class);
        
        $this->factory = new NotificationTrackingTransportFactory(
            $entityManager,
            $repository,
            $analyticsCollector
        );
    }

    public function testSupportsCorrectScheme(): void
    {
        $this->assertTrue($this->factory->supports('notification-tracking://doctrine', []));
        $this->assertFalse($this->factory->supports('redis://localhost', []));
        $this->assertFalse($this->factory->supports('doctrine://default', []));
    }

    public function testCreateTransportBasic(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $dsn = 'notification-tracking://doctrine';
        
        $transport = $this->factory->createTransport($dsn, [], $serializer);
        
        $this->assertInstanceOf(NotificationTrackingTransport::class, $transport);
    }

    public function testDsnParsingDefaults(): void
    {
        $reflection = new \ReflectionClass($this->factory);
        $method = $reflection->getMethod('parseDsn');
        $method->setAccessible(true);

        $result = $method->invoke($this->factory, 'notification-tracking://doctrine', []);

        $this->assertEquals('default', $result['transport_name']);
        $this->assertEquals('default', $result['queue_name']);
        $this->assertTrue($result['analytics_enabled']);
        $this->assertFalse($result['provider_aware_routing']);
        $this->assertEquals(10, $result['batch_size']);
        $this->assertEquals(3, $result['max_retries']);
    }

    public function testDsnParsingWithParameters(): void
    {
        $reflection = new \ReflectionClass($this->factory);
        $method = $reflection->getMethod('parseDsn');
        $method->setAccessible(true);

        $dsn = 'notification-tracking://doctrine?transport_name=email&batch_size=25&analytics_enabled=true&provider_aware_routing=true';
        $result = $method->invoke($this->factory, $dsn, []);

        $this->assertEquals('email', $result['transport_name']);
        $this->assertEquals(25, $result['batch_size']);
        $this->assertTrue($result['analytics_enabled']);
        $this->assertTrue($result['provider_aware_routing']);
    }

    public function testDsnParsingRetryDelays(): void
    {
        $reflection = new \ReflectionClass($this->factory);
        $method = $reflection->getMethod('parseDsn');
        $method->setAccessible(true);

        $dsn = 'notification-tracking://doctrine?retry_delays=1000,5000,30000';
        $result = $method->invoke($this->factory, $dsn, []);

        $this->assertEquals([1000, 5000, 30000], $result['retry_delays']);
    }

    public function testInvalidScheme(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid DSN/');

        $reflection = new \ReflectionClass($this->factory);
        $method = $reflection->getMethod('parseDsn');
        $method->setAccessible(true);

        $method->invoke($this->factory, 'wrong-scheme://doctrine', []);
    }

    public function testInvalidHost(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid DSN/');

        $reflection = new \ReflectionClass($this->factory);
        $method = $reflection->getMethod('parseDsn');
        $method->setAccessible(true);

        $method->invoke($this->factory, 'notification-tracking://redis', []);
    }

    public function testInvalidBatchSize(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/batch_size.*integer/');

        $reflection = new \ReflectionClass($this->factory);
        $method = $reflection->getMethod('parseDsn');
        $method->setAccessible(true);

        $method->invoke($this->factory, 'notification-tracking://doctrine?batch_size=invalid', []);
    }

    public function testBatchSizeTooHigh(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Batch size must be between/');

        $reflection = new \ReflectionClass($this->factory);
        $method = $reflection->getMethod('parseDsn');
        $method->setAccessible(true);

        $method->invoke($this->factory, 'notification-tracking://doctrine?batch_size=101', []);
    }

    public function testInvalidBoolean(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must be a boolean/');

        $reflection = new \ReflectionClass($this->factory);
        $method = $reflection->getMethod('parseDsn');
        $method->setAccessible(true);

        $method->invoke($this->factory, 'notification-tracking://doctrine?analytics_enabled=invalid', []);
    }

    public function testEmptyTransportName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/cannot be empty/');

        $reflection = new \ReflectionClass($this->factory);
        $method = $reflection->getMethod('parseDsn');
        $method->setAccessible(true);

        $method->invoke($this->factory, 'notification-tracking://doctrine?transport_name=', []);
    }

    public function testInvalidCharactersInTransportName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/invalid characters/');

        $reflection = new \ReflectionClass($this->factory);
        $method = $reflection->getMethod('parseDsn');
        $method->setAccessible(true);

        $method->invoke($this->factory, 'notification-tracking://doctrine?transport_name=invalid@name', []);
    }

    public function testInvalidRetryDelays(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/positive integers/');

        $reflection = new \ReflectionClass($this->factory);
        $method = $reflection->getMethod('parseDsn');
        $method->setAccessible(true);

        $method->invoke($this->factory, 'notification-tracking://doctrine?retry_delays=1000,invalid,5000', []);
    }

    public function testNegativeRetryDelay(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/positive integers/');

        $reflection = new \ReflectionClass($this->factory);
        $method = $reflection->getMethod('parseDsn');
        $method->setAccessible(true);

        $method->invoke($this->factory, 'notification-tracking://doctrine?retry_delays=1000,-500,5000', []);
    }

    public function testBooleanConversions(): void
    {
        $reflection = new \ReflectionClass($this->factory);
        $method = $reflection->getMethod('parseDsn');
        $method->setAccessible(true);

        // Test various boolean representations
        $testCases = [
            'true' => true,
            'false' => false,
            '1' => true,
            '0' => false,
            'yes' => true,
            'no' => false,
            'on' => true,
            'off' => false,
        ];

        foreach ($testCases as $input => $expected) {
            $dsn = "notification-tracking://doctrine?analytics_enabled=$input";
            $result = $method->invoke($this->factory, $dsn, []);
            $this->assertEquals($expected, $result['analytics_enabled'], "Failed for input: $input");
        }
    }

    public function testOptionsOverrideDsn(): void
    {
        $reflection = new \ReflectionClass($this->factory);
        $method = $reflection->getMethod('parseDsn');
        $method->setAccessible(true);

        $dsn = 'notification-tracking://doctrine?batch_size=10';
        $options = ['batch_size' => 15, 'max_retries' => 7];
        $result = $method->invoke($this->factory, $dsn, $options);

        // Options should override DSN parameters
        $this->assertEquals(15, $result['batch_size']);
        $this->assertEquals(7, $result['max_retries']);
    }
}
