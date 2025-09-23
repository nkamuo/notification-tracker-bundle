<?php

/**
 * Analytics Field Validation Script
 * 
 * This script validates that the analytics queries use correct entity field names.
 */

require_once 'vendor/autoload.php';

echo "🔍 Validating Analytics Entity Field Mappings...\n\n";

// Check MessageEvent entity fields
$messageEventReflection = new ReflectionClass('Nkamuo\NotificationTrackerBundle\Entity\MessageEvent');
$messageEventProperties = array_map(fn($p) => $p->getName(), $messageEventReflection->getProperties());

echo "✅ MessageEvent Entity Fields:\n";
foreach ($messageEventProperties as $property) {
    echo "   - $property\n";
}

// Check Message entity fields  
$messageReflection = new ReflectionClass('Nkamuo\NotificationTrackerBundle\Entity\Message');
$messageProperties = array_map(fn($p) => $p->getName(), $messageReflection->getProperties());

echo "\n✅ Message Entity Fields:\n";
foreach ($messageProperties as $property) {
    echo "   - $property\n";
}

// Check Notification entity fields
$notificationReflection = new ReflectionClass('Nkamuo\NotificationTrackerBundle\Entity\Notification');
$notificationProperties = array_map(fn($p) => $p->getName(), $notificationReflection->getProperties());

echo "\n✅ Notification Entity Fields:\n";
foreach ($notificationProperties as $property) {
    echo "   - $property\n";
}

// Validate critical field mappings
$validations = [
    'MessageEvent' => [
        'required_fields' => ['eventType', 'occurredAt', 'message', 'recipient'],
        'deprecated_fields' => ['type', 'createdAt'] // These don't exist
    ],
    'Message' => [
        'required_fields' => ['type', 'status', 'transportName', 'createdAt'],
        'notes' => 'type is discriminator column (email, sms, etc.)'
    ],
    'Notification' => [
        'required_fields' => ['type', 'createdAt'],
        'notes' => 'type is notification category'
    ]
];

echo "\n🎯 Field Mapping Validation:\n\n";

foreach ($validations as $entityName => $config) {
    echo "📋 $entityName:\n";
    
    $reflection = new ReflectionClass("Nkamuo\\NotificationTrackerBundle\\Entity\\$entityName");
    $entityFields = array_map(fn($p) => $p->getName(), $reflection->getProperties());
    
    // Check required fields exist
    foreach ($config['required_fields'] as $field) {
        if (in_array($field, $entityFields)) {
            echo "   ✅ $field - EXISTS\n";
        } else {
            echo "   ❌ $field - MISSING\n";
        }
    }
    
    // Check deprecated fields don't exist
    if (isset($config['deprecated_fields'])) {
        foreach ($config['deprecated_fields'] as $field) {
            if (!in_array($field, $entityFields)) {
                echo "   ✅ $field - CORRECTLY NOT PRESENT\n";
            } else {
                echo "   ⚠️  $field - UNEXPECTEDLY EXISTS\n";
            }
        }
    }
    
    if (isset($config['notes'])) {
        echo "   ℹ️  Note: {$config['notes']}\n";
    }
    
    echo "\n";
}

// Check specific query patterns
echo "🔍 Analytics Query Pattern Validation:\n\n";

$analyticsFiles = [
    'src/State/Analytics/RealtimeProvider.php',
    'src/Service/Analytics/AnalyticsService.php'
];

$patterns = [
    'correct' => [
        'e.eventType' => 'MessageEvent.eventType field access',
        'e.occurredAt' => 'MessageEvent.occurredAt field access', 
        'm.type' => 'Message.type discriminator column',
        'n.type' => 'Notification.type field access'
    ],
    'incorrect' => [
        'e.type' => 'MessageEvent does not have "type" field',
        'e.createdAt' => 'MessageEvent does not have "createdAt" field'
    ]
];

foreach ($analyticsFiles as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        echo "📄 Checking $file:\n";
        
        // Check for correct patterns
        foreach ($patterns['correct'] as $pattern => $description) {
            $count = substr_count($content, $pattern);
            if ($count > 0) {
                echo "   ✅ Found $count instances of '$pattern' - $description\n";
            }
        }
        
        // Check for incorrect patterns
        foreach ($patterns['incorrect'] as $pattern => $description) {
            $count = substr_count($content, $pattern);
            if ($count > 0) {
                echo "   ❌ Found $count instances of '$pattern' - $description\n";
            } else {
                echo "   ✅ No instances of '$pattern' - Good!\n";
            }
        }
        echo "\n";
    }
}

echo "✨ Analytics field mapping validation complete!\n";
echo "\n🎯 Summary:\n";
echo "- MessageEvent queries should use: eventType, occurredAt\n";
echo "- Message queries can use: type (discriminator), transportName, status, createdAt\n";
echo "- Notification queries can use: type, createdAt\n";
echo "\n🚀 If all validations pass, the analytics endpoints should work correctly!\n";
