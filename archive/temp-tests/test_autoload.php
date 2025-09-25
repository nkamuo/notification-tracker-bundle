<?php

require_once __DIR__ . '/vendor/autoload.php';

// Test direct require
require_once __DIR__ . '/src/Service/NotificationSender.php';

try {
    if (class_exists('Nkamuo\NotificationTrackerBundle\Service\NotificationSender')) {
        echo "NotificationSender class found via direct require\n";
    } else {
        echo "NotificationSender class NOT found via direct require\n";
    }
} catch (\Error $e) {
    echo "Error loading NotificationSender: " . $e->getMessage() . "\n";
}
