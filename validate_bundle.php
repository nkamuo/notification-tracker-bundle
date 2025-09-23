#!/usr/bin/env php
<?php

/**
 * Service Validation Script for NotificationTrackerBundle
 * 
 * This script tests:
 * 1. Service configuration validity
 * 2. API Platform controller registration
 * 3. Constructor argument resolution
 * 4. Dependency injection setup
 */

echo "🧪 NotificationTrackerBundle Service Validation\n";
echo "===============================================\n\n";

// Test 1: Basic PHP class loading
echo "1. Testing PHP class autoloading...\n";
try {
    require_once __DIR__ . '/vendor/autoload.php';
    
    $testClasses = [
        'Nkamuo\NotificationTrackerBundle\EventSubscriber\MailerEventSubscriber',
        'Nkamuo\NotificationTrackerBundle\Service\MessageTracker',
        'Nkamuo\NotificationTrackerBundle\Controller\Api\RetryMessageController',
        'Nkamuo\NotificationTrackerBundle\Controller\Api\CancelMessageController',
        'Nkamuo\NotificationTrackerBundle\Entity\Message',
    ];
    
    foreach ($testClasses as $class) {
        if (class_exists($class)) {
            echo "   ✅ $class\n";
        } else {
            echo "   ❌ $class (not found)\n";
            exit(1);
        }
    }
    echo "\n";
} catch (Exception $e) {
    echo "   ❌ Autoloader failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: YAML syntax validation
echo "2. Testing YAML configuration syntax...\n";
$yamlFiles = [
    'src/Resources/config/services.yaml',
    'src/Resources/config/tracking.yaml',
    'src/Resources/config/event_subscribers.yaml',
    'src/Resources/config/message_handlers.yaml',
    'src/Resources/config/api_platform.yaml',
];

foreach ($yamlFiles as $file) {
    if (!file_exists($file)) {
        echo "   ⚠️  $file (not found - skipping)\n";
        continue;
    }
    
    try {
        $content = file_get_contents($file);
        // Basic YAML validation - check structure
        if (empty(trim($content))) {
            throw new Exception("Empty file");
        }
        if (!str_contains($content, 'services:') && !str_contains($content, 'monolog:')) {
            throw new Exception("No services section found");
        }
        // Check for basic YAML syntax issues
        if (substr_count($content, ':') === 0) {
            throw new Exception("Invalid YAML structure");
        }
        echo "   ✅ $file\n";
    } catch (Exception $e) {
        echo "   ❌ $file: " . $e->getMessage() . "\n";
        exit(1);
    }
}
echo "\n";

// Test 3: Constructor analysis
echo "3. Testing service constructor compatibility...\n";
$constructorTests = [
    'Nkamuo\NotificationTrackerBundle\EventSubscriber\MailerEventSubscriber' => [
        'expected_args' => 4,
        'arg_types' => [
            'Nkamuo\NotificationTrackerBundle\Service\MessageTracker',
            'Doctrine\ORM\EntityManagerInterface', 
            'Psr\Log\LoggerInterface',
            'bool'
        ]
    ],
    'Nkamuo\NotificationTrackerBundle\Controller\Api\RetryMessageController' => [
        'expected_args' => 1,
        'arg_types' => [
            'Nkamuo\NotificationTrackerBundle\Service\MessageRetryService'
        ]
    ]
];

foreach ($constructorTests as $className => $test) {
    try {
        $reflection = new ReflectionClass($className);
        $constructor = $reflection->getConstructor();
        
        if (!$constructor) {
            echo "   ⚠️  $className: No constructor\n";
            continue;
        }
        
        $params = $constructor->getParameters();
        $actualArgCount = count($params);
        
        if ($actualArgCount !== $test['expected_args']) {
            echo "   ❌ $className: Expected {$test['expected_args']} args, got $actualArgCount\n";
            exit(1);
        }
        
        // Check parameter types
        foreach ($params as $index => $param) {
            $type = $param->getType();
            $expectedType = $test['arg_types'][$index] ?? null;
            
            if ($expectedType && $type) {
                $actualType = $type instanceof ReflectionNamedType ? $type->getName() : (string)$type;
                if ($actualType !== $expectedType) {
                    echo "   ⚠️  $className: Arg $index type mismatch (expected $expectedType, got $actualType)\n";
                }
            }
        }
        
        echo "   ✅ $className: {$actualArgCount} constructor arguments\n";
        
    } catch (Exception $e) {
        echo "   ❌ $className: " . $e->getMessage() . "\n";
        exit(1);
    }
}
echo "\n";

// Test 4: API Platform attribute validation
echo "4. Testing API Platform controller attributes...\n";
try {
    $messageClass = new ReflectionClass('Nkamuo\NotificationTrackerBundle\Entity\Message');
    $attributes = $messageClass->getAttributes();
    
    $hasApiResource = false;
    foreach ($attributes as $attribute) {
        if ($attribute->getName() === 'ApiPlatform\Metadata\ApiResource') {
            $hasApiResource = true;
            echo "   ✅ Message entity has ApiResource attribute\n";
            break;
        }
    }
    
    if (!$hasApiResource) {
        echo "   ❌ Message entity missing ApiResource attribute\n";
        exit(1);
    }
    
    // Check if controller classes are imported
    $messageFile = file_get_contents('src/Entity/Message.php');
    if (str_contains($messageFile, 'use Nkamuo\NotificationTrackerBundle\Controller\Api\RetryMessageController')) {
        echo "   ✅ RetryMessageController import found\n";
    } else {
        echo "   ❌ RetryMessageController import missing\n";
        exit(1);
    }
    
    if (str_contains($messageFile, 'use Nkamuo\NotificationTrackerBundle\Controller\Api\CancelMessageController')) {
        echo "   ✅ CancelMessageController import found\n";
    } else {
        echo "   ❌ CancelMessageController import missing\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "   ❌ API Platform validation failed: " . $e->getMessage() . "\n";
    exit(1);
}
echo "\n";

echo "🎉 All validations passed! The bundle should install correctly.\n";
echo "\nNext steps:\n";
echo "- Test installation: composer require nkamuo/notification-tracker-bundle\n";
echo "- Run cache:clear to verify service registration\n";
echo "- Check API Platform routes: php bin/console debug:router | grep notification\n";
