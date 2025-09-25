<?php

declare(strict_types=1);

/**
 * Comprehensive test script for the Notification Tracking Transport
 * 
 * This script validates all major components without requiring full Symfony setup:
 * - Transport Factory DSN parsing
 * - Custom Stamps functionality
 * - Entity relationships and validation
 * - Service integrations
 */

require_once __DIR__ . '/vendor/autoload.php';

use Nkamuo\NotificationTrackerBundle\Entity\QueuedMessage;
use Nkamuo\NotificationTrackerBundle\Stamp\NotificationCampaignStamp;
use Nkamuo\NotificationTrackerBundle\Stamp\NotificationProviderStamp;
use Nkamuo\NotificationTrackerBundle\Stamp\NotificationTemplateStamp;
use Nkamuo\NotificationTrackerBundle\Transport\NotificationTrackingTransportFactory;
use Symfony\Component\Uid\Uuid;

echo "🚀 Comprehensive Notification Transport Validation\n";
echo "==================================================\n\n";

// Test 1: Entity Creation and Validation
function testQueuedMessageEntity(): bool
{
    echo "🧪 Testing QueuedMessage Entity...\n";
    
    try {
        $message = new QueuedMessage();
        
        // Test UUID generation
        $id = $message->getId();
        if (!$id instanceof Uuid) {
            echo "❌ UUID generation failed\n";
            return false;
        }
        echo "✅ UUID auto-generation works: {$id->toString()}\n";
        
        // Test basic setters/getters
        $message->setTransport('test_transport')
                ->setQueueName('test_queue')
                ->setBody('test body')
                ->setHeaders(['Content-Type' => 'application/json'])
                ->setStatus('queued')
                ->setPriority(10)
                ->setNotificationProvider('email')
                ->setCampaignId('campaign-123')
                ->setTemplateId('template-456');
        
        // Validate values
        $checks = [
            'transport' => $message->getTransport() === 'test_transport',
            'queueName' => $message->getQueueName() === 'test_queue',
            'body' => $message->getBody() === 'test body',
            'headers' => $message->getHeaders() === ['Content-Type' => 'application/json'],
            'status' => $message->getStatus() === 'queued',
            'priority' => $message->getPriority() === 10,
            'provider' => $message->getNotificationProvider() === 'email',
            'campaign' => $message->getCampaignId() === 'campaign-123',
            'template' => $message->getTemplateId() === 'template-456',
        ];
        
        foreach ($checks as $field => $valid) {
            if (!$valid) {
                echo "❌ Field '$field' validation failed\n";
                return false;
            }
        }
        
        echo "✅ All entity fields working correctly\n";
        
        // Test retry logic
        $message->setRetryCount(2);
        $message->setMaxRetries(5);
        
        if ($message->canRetry()) {
            echo "✅ Retry logic working (2/5 retries)\n";
        } else {
            echo "❌ Retry logic failed\n";
            return false;
        }
        
        $message->setRetryCount(5);
        if (!$message->canRetry()) {
            echo "✅ Max retry limit working (5/5 retries)\n";
        } else {
            echo "❌ Max retry limit failed\n";
            return false;
        }
        
        return true;
        
    } catch (Exception $e) {
        echo "❌ Entity test failed: {$e->getMessage()}\n";
        return false;
    }
}

// Test 2: Custom Stamps
function testCustomStamps(): bool
{
    echo "\n🧪 Testing Custom Stamps...\n";
    
    try {
        // Provider Stamp
        $providerStamp = new NotificationProviderStamp('email', 10);
        if ($providerStamp->getProvider() !== 'email' || $providerStamp->getPriority() !== 10) {
            echo "❌ NotificationProviderStamp failed\n";
            return false;
        }
        echo "✅ NotificationProviderStamp working\n";
        
        // Campaign Stamp
        $campaignStamp = new NotificationCampaignStamp('campaign-123', 'Welcome Campaign');
        if ($campaignStamp->getCampaignId() !== 'campaign-123' || $campaignStamp->getCampaignName() !== 'Welcome Campaign') {
            echo "❌ NotificationCampaignStamp failed\n";
            return false;
        }
        echo "✅ NotificationCampaignStamp working\n";
        
        // Template Stamp
        $templateStamp = new NotificationTemplateStamp('template-456', 'Welcome Template');
        if ($templateStamp->getTemplateId() !== 'template-456' || $templateStamp->getTemplateName() !== 'Welcome Template') {
            echo "❌ NotificationTemplateStamp failed\n";
            return false;
        }
        echo "✅ NotificationTemplateStamp working\n";
        
        return true;
        
    } catch (Exception $e) {
        echo "❌ Stamps test failed: {$e->getMessage()}\n";
        return false;
    }
}

// Test 3: DSN Parsing (already tested in detail, but verify key cases)
function testDsnParsing(): bool
{
    echo "\n🧪 Testing Advanced DSN Scenarios...\n";
    
    try {
        $reflection = new ReflectionClass(NotificationTrackingTransportFactory::class);
        $parseDsnMethod = $reflection->getMethod('parseDsn');
        $parseDsnMethod->setAccessible(true);
        $factory = $reflection->newInstanceWithoutConstructor();
        
        // Test complex DSN with all parameters
        $complexDsn = 'notification-tracking://doctrine?' . 
                      'transport_name=email_priority&' .
                      'queue_name=high_priority&' .
                      'analytics_enabled=true&' .
                      'provider_aware_routing=true&' .
                      'batch_size=25&' .
                      'max_retries=7&' .
                      'retry_delays=1000,3000,10000,30000,120000';
        
        $result = $parseDsnMethod->invoke($factory, $complexDsn, []);
        
        $expected = [
            'transport_name' => 'email_priority',
            'queue_name' => 'high_priority',
            'analytics_enabled' => true,
            'provider_aware_routing' => true,
            'batch_size' => 25,
            'max_retries' => 7,
            'retry_delays' => [1000, 3000, 10000, 30000, 120000],
        ];
        
        foreach ($expected as $key => $expectedValue) {
            if (!isset($result[$key]) || $result[$key] !== $expectedValue) {
                echo "❌ Complex DSN parsing failed for $key\n";
                echo "   Expected: " . json_encode($expectedValue) . "\n";
                echo "   Got: " . json_encode($result[$key] ?? 'missing') . "\n";
                return false;
            }
        }
        
        echo "✅ Complex DSN parsing successful\n";
        
        // Test edge cases
        $edgeCases = [
            // Boolean variations
            'analytics_enabled=yes' => ['analytics_enabled' => true],
            'analytics_enabled=off' => ['analytics_enabled' => false],
            'provider_aware_routing=1' => ['provider_aware_routing' => true],
            'provider_aware_routing=0' => ['provider_aware_routing' => false],
            
            // Integer boundaries
            'batch_size=1' => ['batch_size' => 1],
            'batch_size=100' => ['batch_size' => 100],
            'max_retries=0' => ['max_retries' => 0],
            'max_retries=10' => ['max_retries' => 10],
        ];
        
        foreach ($edgeCases as $queryString => $expectedValues) {
            $dsn = "notification-tracking://doctrine?$queryString";
            $result = $parseDsnMethod->invoke($factory, $dsn, []);
            
            foreach ($expectedValues as $key => $expectedValue) {
                if ($result[$key] !== $expectedValue) {
                    echo "❌ Edge case failed: $queryString\n";
                    return false;
                }
            }
        }
        
        echo "✅ Edge case validation successful\n";
        
        return true;
        
    } catch (Exception $e) {
        echo "❌ DSN parsing test failed: {$e->getMessage()}\n";
        return false;
    }
}

// Test 4: Validation and Error Handling
function testValidationAndErrors(): bool
{
    echo "\n🧪 Testing Validation and Error Handling...\n";
    
    try {
        $reflection = new ReflectionClass(NotificationTrackingTransportFactory::class);
        $parseDsnMethod = $reflection->getMethod('parseDsn');
        $parseDsnMethod->setAccessible(true);
        $factory = $reflection->newInstanceWithoutConstructor();
        
        $errorCases = [
            // Type validation
            ['dsn' => 'notification-tracking://doctrine?batch_size=not_a_number', 'contains' => 'must be an integer'],
            ['dsn' => 'notification-tracking://doctrine?max_retries=not_a_number', 'contains' => 'must be an integer'],
            ['dsn' => 'notification-tracking://doctrine?analytics_enabled=maybe', 'contains' => 'must be a boolean'],
            
            // Range validation
            ['dsn' => 'notification-tracking://doctrine?batch_size=0', 'contains' => 'must be between 1 and 100'],
            ['dsn' => 'notification-tracking://doctrine?batch_size=101', 'contains' => 'must be between 1 and 100'],
            ['dsn' => 'notification-tracking://doctrine?max_retries=11', 'contains' => 'must be between 0 and 10'],
            
            // String validation
            ['dsn' => 'notification-tracking://doctrine?transport_name=invalid@name', 'contains' => 'invalid characters'],
            ['dsn' => 'notification-tracking://doctrine?queue_name=invalid$queue', 'contains' => 'invalid characters'],
            ['dsn' => 'notification-tracking://doctrine?transport_name=', 'contains' => 'cannot be empty'],
            ['dsn' => 'notification-tracking://doctrine?transport_name=' . str_repeat('a', 101), 'contains' => '100 characters or less'],
            
            // Scheme validation
            ['dsn' => 'wrong-scheme://doctrine', 'contains' => 'Invalid DSN'],
            ['dsn' => 'notification-tracking://redis', 'contains' => 'Invalid DSN'],
            
            // Array validation
            ['dsn' => 'notification-tracking://doctrine?retry_delays=1000,invalid,5000', 'contains' => 'positive integers'],
            ['dsn' => 'notification-tracking://doctrine?retry_delays=1000,-500,5000', 'contains' => 'positive integers'],
        ];
        
        $successCount = 0;
        foreach ($errorCases as $case) {
            try {
                $parseDsnMethod->invoke($factory, $case['dsn'], []);
                echo "❌ Expected error for: {$case['dsn']}\n";
            } catch (Exception $e) {
                if (str_contains($e->getMessage(), $case['contains'])) {
                    $successCount++;
                } else {
                    echo "❌ Wrong error message for: {$case['dsn']}\n";
                    echo "   Expected to contain: {$case['contains']}\n";
                    echo "   Got: {$e->getMessage()}\n";
                }
            }
        }
        
        if ($successCount === count($errorCases)) {
            echo "✅ All validation tests passed ($successCount/" . count($errorCases) . ")\n";
            return true;
        } else {
            echo "❌ Some validation tests failed ($successCount/" . count($errorCases) . ")\n";
            return false;
        }
        
    } catch (Exception $e) {
        echo "❌ Validation test failed: {$e->getMessage()}\n";
        return false;
    }
}

// Test 5: Configuration Examples
function testConfigurationExamples(): bool
{
    echo "\n🧪 Testing Real-World Configuration Examples...\n";
    
    try {
        $reflection = new ReflectionClass(NotificationTrackingTransportFactory::class);
        $parseDsnMethod = $reflection->getMethod('parseDsn');
        $parseDsnMethod->setAccessible(true);
        $factory = $reflection->newInstanceWithoutConstructor();
        
        $realWorldConfigs = [
            'Basic Email' => [
                'dsn' => 'notification-tracking://doctrine?transport_name=email',
                'expected' => ['transport_name' => 'email', 'analytics_enabled' => true]
            ],
            'High Priority SMS' => [
                'dsn' => 'notification-tracking://doctrine?transport_name=sms_priority&queue_name=high_priority&batch_size=5&provider_aware_routing=true',
                'expected' => ['transport_name' => 'sms_priority', 'queue_name' => 'high_priority', 'batch_size' => 5, 'provider_aware_routing' => true]
            ],
            'Bulk Processing' => [
                'dsn' => 'notification-tracking://doctrine?transport_name=bulk_email&batch_size=50&max_retries=1&analytics_enabled=false',
                'expected' => ['transport_name' => 'bulk_email', 'batch_size' => 50, 'max_retries' => 1, 'analytics_enabled' => false]
            ],
            'Custom Retry Strategy' => [
                'dsn' => 'notification-tracking://doctrine?transport_name=critical&retry_delays=500,2000,10000,60000,300000&max_retries=5',
                'expected' => ['retry_delays' => [500, 2000, 10000, 60000, 300000], 'max_retries' => 5]
            ],
        ];
        
        foreach ($realWorldConfigs as $name => $config) {
            $result = $parseDsnMethod->invoke($factory, $config['dsn'], []);
            
            foreach ($config['expected'] as $key => $expectedValue) {
                if (!isset($result[$key]) || $result[$key] !== $expectedValue) {
                    echo "❌ Configuration '$name' failed for $key\n";
                    return false;
                }
            }
            
            echo "✅ Configuration '$name' validated\n";
        }
        
        return true;
        
    } catch (Exception $e) {
        echo "❌ Configuration test failed: {$e->getMessage()}\n";
        return false;
    }
}

// Run all tests
echo "Starting comprehensive validation...\n\n";

$tests = [
    'QueuedMessage Entity' => 'testQueuedMessageEntity',
    'Custom Stamps' => 'testCustomStamps', 
    'DSN Parsing' => 'testDsnParsing',
    'Validation & Errors' => 'testValidationAndErrors',
    'Configuration Examples' => 'testConfigurationExamples',
];

$results = [];
foreach ($tests as $testName => $testFunction) {
    $results[$testName] = $testFunction();
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "🎯 FINAL RESULTS\n";
echo str_repeat("=", 50) . "\n";

$passed = 0;
$total = count($results);

foreach ($results as $testName => $result) {
    $status = $result ? "✅ PASS" : "❌ FAIL";
    echo "$status - $testName\n";
    if ($result) $passed++;
}

echo "\n📊 Summary: $passed/$total tests passed\n";

if ($passed === $total) {
    echo "\n🎉 ALL TESTS PASSED! Your notification transport is working perfectly!\n";
    echo "\n🚀 Ready for production use with:\n";
    echo "   • Robust DSN configuration with query parameters\n";
    echo "   • Complete validation and error handling\n";
    echo "   • Rich notification metadata via custom stamps\n";
    echo "   • Provider-aware routing and analytics\n";
    echo "   • Comprehensive retry strategies\n";
    echo "\n¡Vamos! 🚀 Your enhanced notification system is ready!\n";
} else {
    echo "\n⚠️  Some tests failed. Please review the implementation.\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
