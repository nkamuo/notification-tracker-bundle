<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Tests\Unit\Transport;

use Doctrine\ORM\EntityManagerInterface;
use Nkamuo\NotificationTrackerBundle\Repository\QueuedMessageRepository;
use Nkamuo\NotificationTrackerBundle\Service\NotificationAnalyticsCollector;
use Nkamuo\NotificationTrackerBundle\Transport\NotificationTrackingTransport;
use Nkamuo\NotificationTrackerBundle\Transport\NotificationTrackingTransportFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class NotificationTrackingTransportFactoryTest extends TestCase
{
    private NotificationTrackingTransportFactory $factory;
    private EntityManagerInterface&MockObject $entityManager;
    private QueuedMessageRepository&MockObject $repository;
    private NotificationAnalyticsCollector&MockObject $analyticsCollector;
    private SerializerInterface&MockObject $serializer;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(QueuedMessageRepository::class);
        $this->analyticsCollector = $this->createMock(NotificationAnalyticsCollector::class);
        $this->serializer = $this->createMock(SerializerInterface::class);
        
        $this->factory = new NotificationTrackingTransportFactory(
            $this->entityManager,
            $this->repository,
            $this->analyticsCollector
        );
    }

    public function testSupportsCorrectScheme(): void
    {
        $this->assertTrue($this->factory->supports('notification-tracking://doctrine', []));
        $this->assertFalse($this->factory->supports('redis://localhost', []));
        $this->assertFalse($this->factory->supports('doctrine://default', []));
    }

    public function testCreateTransportWithDefaultOptions(): void
    {
        $dsn = 'notification-tracking://doctrine';
        $transport = $this->factory->createTransport($dsn, [], $this->serializer);

        $this->assertInstanceOf(NotificationTrackingTransport::class, $transport);
    }

    public function testCreateTransportWithCustomOptions(): void
    {
        $dsn = 'notification-tracking://doctrine?transport_name=email&batch_size=20&analytics_enabled=false';
        $transport = $this->factory->createTransport($dsn, [], $this->serializer);

        $this->assertInstanceOf(NotificationTrackingTransport::class, $transport);
    }

    public function testParseDsnWithDefaults(): void
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
        $this->assertEquals([1000, 5000, 30000], $result['retry_delays']);
    }

    public function testParseDsnWithQueryParameters(): void
    {
        $reflection = new \ReflectionClass($this->factory);
        $method = $reflection->getMethod('parseDsn');
        $method->setAccessible(true);

        $dsn = 'notification-tracking://doctrine?transport_name=email&queue_name=priority&analytics_enabled=true&provider_aware_routing=true&batch_size=25&max_retries=5&retry_delays=2000,10000,60000,300000';
        $result = $method->invoke($this->factory, $dsn, []);

        $this->assertEquals('email', $result['transport_name']);
        $this->assertEquals('priority', $result['queue_name']);
        $this->assertTrue($result['analytics_enabled']);
        $this->assertTrue($result['provider_aware_routing']);
        $this->assertEquals(25, $result['batch_size']);
        $this->assertEquals(5, $result['max_retries']);
        $this->assertEquals([2000, 10000, 60000, 300000], $result['retry_delays']);
    }

    public function testParseDsnWithOptionsOverride(): void
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

    public function testInvalidDsnScheme(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid DSN "wrong-scheme://doctrine". Expected format: notification-tracking://doctrine?options');

        $reflection = new \ReflectionClass($this->factory);
        $method = $reflection->getMethod('parseDsn');
        $method->setAccessible(true);

        $method->invoke($this->factory, 'wrong-scheme://doctrine', []);
    }

    public function testInvalidDsnHost(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid DSN "notification-tracking://redis". Expected format: notification-tracking://doctrine?options');

        $reflection = new \ReflectionClass($this->factory);
        $method = $reflection->getMethod('parseDsn');
        $method->setAccessible(true);

        $method->invoke($this->factory, 'notification-tracking://redis', []);
    }

    /**
     * @dataProvider invalidParameterProvider
     */
    public function testInvalidParameters(string $dsn, string $expectedMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        $reflection = new \ReflectionClass($this->factory);
        $method = $reflection->getMethod('parseDsn');
        $method->setAccessible(true);

        $method->invoke($this->factory, $dsn, []);
    }

    public function invalidParameterProvider(): array
    {
        return [
            'invalid batch_size' => [
                'notification-tracking://doctrine?batch_size=invalid',
                'Option "batch_size" must be an integer, got "invalid".'
            ],
            'batch_size too low' => [
                'notification-tracking://doctrine?batch_size=0',
                'Batch size must be between 1 and 100, got 0.'
            ],
            'batch_size too high' => [
                'notification-tracking://doctrine?batch_size=101',
                'Batch size must be between 1 and 100, got 101.'
            ],
            'invalid max_retries' => [
                'notification-tracking://doctrine?max_retries=invalid',
                'Option "max_retries" must be an integer, got "invalid".'
            ],
            'max_retries too high' => [
                'notification-tracking://doctrine?max_retries=11',
                'Max retries must be between 0 and 10, got 11.'
            ],
            'invalid analytics_enabled' => [
                'notification-tracking://doctrine?analytics_enabled=invalid',
                'Option "analytics_enabled" must be a boolean, got "invalid".'
            ],
            'invalid provider_aware_routing' => [
                'notification-tracking://doctrine?provider_aware_routing=invalid',
                'Option "provider_aware_routing" must be a boolean, got "invalid".'
            ],
            'invalid transport_name characters' => [
                'notification-tracking://doctrine?transport_name=invalid@name',
                'Transport name "invalid@name" contains invalid characters. Only alphanumeric, underscore, and hyphen allowed.'
            ],
            'transport_name too long' => [
                'notification-tracking://doctrine?transport_name=' . str_repeat('a', 101),
                'Transport name must be 100 characters or less, got 101 characters.'
            ],
            'invalid queue_name characters' => [
                'notification-tracking://doctrine?queue_name=invalid$queue',
                'Queue name "invalid$queue" contains invalid characters. Only alphanumeric, underscore, and hyphen allowed.'
            ],
            'empty transport_name' => [
                'notification-tracking://doctrine?transport_name=',
                'Transport name cannot be empty.'
            ],
            'empty queue_name' => [
                'notification-tracking://doctrine?queue_name=',
                'Queue name cannot be empty.'
            ],
        ];
    }

    public function testValidBooleanConversion(): void
    {
        $reflection = new \ReflectionClass($this->factory);
        $method = $reflection->getMethod('parseDsn');
        $method->setAccessible(true);

        // Test various boolean representations
        $testCases = [
            'analytics_enabled=true' => true,
            'analytics_enabled=false' => false,
            'analytics_enabled=1' => true,
            'analytics_enabled=0' => false,
            'analytics_enabled=yes' => true,
            'analytics_enabled=no' => false,
            'analytics_enabled=on' => true,
            'analytics_enabled=off' => false,
        ];

        foreach ($testCases as $param => $expected) {
            $dsn = "notification-tracking://doctrine?$param";
            $result = $method->invoke($this->factory, $dsn, []);
            $this->assertEquals($expected, $result['analytics_enabled'], "Failed for parameter: $param");
        }
    }

    public function testRetryDelaysParsing(): void
    {
        $reflection = new \ReflectionClass($this->factory);
        $method = $reflection->getMethod('parseDsn');
        $method->setAccessible(true);

        $dsn = 'notification-tracking://doctrine?retry_delays=1000,5000,30000,120000';
        $result = $method->invoke($this->factory, $dsn, []);

        $this->assertEquals([1000, 5000, 30000, 120000], $result['retry_delays']);
    }

    public function testInvalidRetryDelays(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid retry delay "invalid". All delays must be positive integers.');

        $reflection = new \ReflectionClass($this->factory);
        $method = $reflection->getMethod('parseDsn');
        $method->setAccessible(true);

        $method->invoke($this->factory, 'notification-tracking://doctrine?retry_delays=1000,invalid,5000', []);
    }

    public function testNegativeRetryDelay(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid retry delay "-1000". All delays must be positive integers.');

        $reflection = new \ReflectionClass($this->factory);
        $method = $reflection->getMethod('parseDsn');
        $method->setAccessible(true);

        $method->invoke($this->factory, 'notification-tracking://doctrine?retry_delays=1000,-1000,5000', []);
    }
}
