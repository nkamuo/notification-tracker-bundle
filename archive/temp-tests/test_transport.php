<?php

declare(strict_types=1);

/**
 * Simple test script to validate the DSN parsing of NotificationTrackingTransportFactory
 */

require_once __DIR__ . '/vendor/autoload.php';

use Nkamuo\NotificationTrackerBundle\Transport\NotificationTrackingTransportFactory;

// Test DSN parsing
function testDsnParsing(): void
{
    echo "🧪 Testing DSN Parsing...\n\n";

    // Create a minimal factory for testing DSN parsing
    $reflection = new ReflectionClass(NotificationTrackingTransportFactory::class);
    $parseDsnMethod = $reflection->getMethod('parseDsn');
    $parseDsnMethod->setAccessible(true);

    // Create factory instance with null dependencies for testing
    $factory = $reflection->newInstanceWithoutConstructor();

    // Test cases
    $testCases = [
        [
            'dsn' => 'notification-tracking://doctrine',
            'options' => [],
            'expected' => [
                'transport_name' => 'default',
                'queue_name' => 'default',
                'analytics_enabled' => true,
                'provider_aware_routing' => false,
                'batch_size' => 10,
                'max_retries' => 3,
            ]
        ],
        [
            'dsn' => 'notification-tracking://doctrine?transport_name=email&analytics_enabled=true&batch_size=20',
            'options' => [],
            'expected' => [
                'transport_name' => 'email',
                'queue_name' => 'default',
                'analytics_enabled' => true,
                'provider_aware_routing' => false,
                'batch_size' => 20,
                'max_retries' => 3,
            ]
        ],
        [
            'dsn' => 'notification-tracking://doctrine?queue_name=sms_queue&provider_aware_routing=true&max_retries=5',
            'options' => ['batch_size' => 15], // Options override DSN
            'expected' => [
                'transport_name' => 'default',
                'queue_name' => 'sms_queue',
                'analytics_enabled' => true,
                'provider_aware_routing' => true,
                'batch_size' => 15, // Overridden by options
                'max_retries' => 5,
            ]
        ],
        [
            'dsn' => 'notification-tracking://doctrine?retry_delays=1000,5000,30000,120000',
            'options' => [],
            'expected' => [
                'retry_delays' => [1000, 5000, 30000, 120000],
            ]
        ]
    ];

    foreach ($testCases as $i => $testCase) {
        echo "Test Case " . ($i + 1) . ":\n";
        echo "DSN: {$testCase['dsn']}\n";
        echo "Options: " . json_encode($testCase['options']) . "\n";

        try {
            $result = $parseDsnMethod->invoke($factory, $testCase['dsn'], $testCase['options']);
            
            echo "✅ Parsed successfully\n";
            
            foreach ($testCase['expected'] as $key => $expectedValue) {
                if (!isset($result[$key]) || $result[$key] !== $expectedValue) {
                    echo "❌ Expected $key = " . json_encode($expectedValue) . ", got " . json_encode($result[$key] ?? 'missing') . "\n";
                } else {
                    echo "✅ $key = " . json_encode($result[$key]) . "\n";
                }
            }
            
        } catch (Exception $e) {
            echo "❌ Failed: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
}

function testInvalidDsn(): void
{
    echo "🧪 Testing Invalid DSN Handling...\n\n";

    $reflection = new ReflectionClass(NotificationTrackingTransportFactory::class);
    $parseDsnMethod = $reflection->getMethod('parseDsn');
    $parseDsnMethod->setAccessible(true);
    $factory = $reflection->newInstanceWithoutConstructor();

    $invalidCases = [
        'wrong-scheme://doctrine',
        'notification-tracking://doctrine?batch_size=invalid',
        'notification-tracking://doctrine?max_retries=20', // Too high
        'notification-tracking://doctrine?transport_name=invalid-name!',
    ];

    foreach ($invalidCases as $invalidDsn) {
        echo "Testing: $invalidDsn\n";
        try {
            $parseDsnMethod->invoke($factory, $invalidDsn, []);
            echo "❌ Should have thrown exception\n";
        } catch (Exception $e) {
            echo "✅ Correctly rejected: " . $e->getMessage() . "\n";
        }
        echo "\n";
    }
}

// Run tests
echo "🚀 Custom Notification Transport DSN Validation\n";
echo "================================================\n\n";

testDsnParsing();
testInvalidDsn();

echo "🎉 DSN parsing tests completed!\n";
echo "\nTo use this transport in your application:\n\n";
echo "1. Configure in config/packages/messenger.yaml:\n";
echo "   framework:\n";
echo "     messenger:\n";
echo "       transports:\n";
echo "         notification_email:\n";
echo "           dsn: 'notification-tracking://doctrine?transport_name=email&analytics_enabled=true'\n\n";
echo "2. Dispatch messages with stamps:\n";
echo "   \$bus->dispatch(\$message, [\n";
echo "     new NotificationProviderStamp('email', 10),\n";
echo "     new NotificationCampaignStamp('campaign-id')\n";
echo "   ]);\n\n";
echo "3. Monitor via API endpoints:\n";
echo "   GET /api/queue/messages - List queued messages\n";
echo "   GET /api/queue/stats - Get queue statistics\n";
echo "   GET /api/queue/health - Check queue health\n\n";
echo "¡Vamos! 🚀 Your custom notification transport is ready!\n";
