<?php

require_once 'vendor/autoload.php';

echo "=== Testing Class Autoloading After Namespace Fix ===\n\n";

// Test classes that were causing the case mismatch error
$testClasses = [
    'Nkamuo\NotificationTrackerBundle\DTO\ContactChannelDTO',
    'Nkamuo\NotificationTrackerBundle\DTO\ContactDTO', 
    'Nkamuo\NotificationTrackerBundle\DTO\Analytics\EngagementAnalyticsDto',
    'Nkamuo\NotificationTrackerBundle\DTO\Queue\QueueStatusDto',
];

foreach ($testClasses as $className) {
    echo "Testing class: $className\n";
    
    if (class_exists($className)) {
        echo "   ✅ Class exists and can be autoloaded\n";
        
        // Get the file path where the class is defined
        $reflection = new ReflectionClass($className);
        $fileName = $reflection->getFileName();
        echo "   📁 File: $fileName\n";
        
        // Verify namespace matches
        $namespace = $reflection->getNamespaceName();
        echo "   🏷️  Namespace: $namespace\n";
        
    } else {
        echo "   ❌ Class NOT found - autoloading failed\n";
    }
    echo "\n";
}

echo "=== Case Sensitivity Test ===\n\n";

// Test that the old case-mismatched namespace doesn't work
$badClasses = [
    'Nkamuo\NotificationTrackerBundle\Dto\ContactChannelDTO',  // Wrong: Dto instead of DTO
    'Nkamuo\NotificationTrackerBundle\Dto\Analytics\EngagementAnalyticsDto',  // Wrong: Dto instead of DTO
];

foreach ($badClasses as $className) {
    echo "Testing (should fail): $className\n";
    
    if (class_exists($className)) {
        echo "   ❌ Class exists (this is wrong - case mismatch should prevent loading)\n";
    } else {
        echo "   ✅ Class NOT found (correct - case mismatch properly rejected)\n";
    }
    echo "\n";
}

echo "✅ All tests completed!\n";
echo "The case mismatch issue should now be resolved.\n";
