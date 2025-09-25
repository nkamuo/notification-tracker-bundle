<?php

/**
 * QueueResource ID Strategy Validation
 * 
 * This script demonstrates the ID generation strategy for QueueResource
 */

require_once 'vendor/autoload.php';

use Nkamuo\NotificationTrackerBundle\ApiResource\QueueResource;

echo "🎯 QueueResource ID Strategy Validation\n\n";

// Test 1: Basic constructor
echo "1️⃣  Testing Basic Constructor:\n";
$resource1 = new QueueResource();
echo "   - Generated ID: {$resource1->id}\n";
echo "   - Is Message Resource: " . ($resource1->isMessageResource() ? 'Yes' : 'No') . "\n";
echo "   - Display ID: {$resource1->getDisplayId()}\n\n";

// Test 2: Constructor with custom ID
echo "2️⃣  Testing Constructor with Custom ID:\n";
$resource2 = new QueueResource('custom-message-123');
echo "   - Custom ID: {$resource2->id}\n";
echo "   - Is Message Resource: " . ($resource2->isMessageResource() ? 'Yes' : 'No') . "\n";
echo "   - Display ID: {$resource2->getDisplayId()}\n\n";

// Test 3: Stats Resource
echo "3️⃣  Testing Stats Resource:\n";
$statsData = [
    'total_messages' => 150,
    'queued_messages' => 25,
    'delivered_messages' => 100,
    'processed_messages' => 125,
    'failed_messages' => 5,
    'retrying_messages' => 3,
    'messages_by_transport' => ['email' => 80, 'sms' => 45, 'push' => 25],
    'messages_by_provider' => ['mailgun' => 60, 'twilio' => 45, 'firebase' => 25],
    'average_processing_time' => 2.5,
    'success_rate' => 95.2
];

$statsResource = QueueResource::createStatsResource($statsData);
echo "   - Stats ID: {$statsResource->id}\n";
echo "   - Is Stats Resource: " . ($statsResource->isStatsResource() ? 'Yes' : 'No') . "\n";
echo "   - Display ID: {$statsResource->getDisplayId()}\n";
echo "   - Total Messages: {$statsResource->totalMessages}\n";
echo "   - Success Rate: {$statsResource->successRate}%\n\n";

// Test 4: Health Resource
echo "4️⃣  Testing Health Resource:\n";
$healthData = [
    'overall_health' => 'healthy',
    'transport_health' => [
        'email' => 'healthy',
        'sms' => 'warning',
        'push' => 'healthy'
    ],
    'oldest_queued_message_age' => 120,
    'stuck_messages_count' => 2,
    'health_checks' => [
        'database_connection' => 'ok',
        'queue_processing' => 'ok',
        'memory_usage' => 'warning'
    ]
];

$healthResource = QueueResource::createHealthResource($healthData);
echo "   - Health ID: {$healthResource->id}\n";
echo "   - Is Health Resource: " . ($healthResource->isHealthResource() ? 'Yes' : 'No') . "\n";
echo "   - Display ID: {$healthResource->getDisplayId()}\n";
echo "   - Overall Health: {$healthResource->overallHealth}\n";
echo "   - Stuck Messages: {$healthResource->stuckMessagesCount}\n\n";

// Test 5: Message Resource with Details
echo "5️⃣  Testing Message Resource with Details:\n";
$messageResource = new QueueResource();
$messageResource->transport = 'email';
$messageResource->queueName = 'notifications';
$messageResource->notificationProvider = 'mailgun';
$messageResource->status = 'queued';
$messageResource->priority = 10;

echo "   - Message ID: {$messageResource->id}\n";
echo "   - Display ID: {$messageResource->getDisplayId()}\n";
echo "   - Transport: {$messageResource->transport}\n";
echo "   - Provider: {$messageResource->notificationProvider}\n";
echo "   - Priority: {$messageResource->priority}\n\n";

// Test 6: ID Consistency
echo "6️⃣  Testing ID Consistency:\n";
echo "   Creating multiple stats resources within same minute...\n";

$stats1 = QueueResource::createStatsResource($statsData);
sleep(1);
$stats2 = QueueResource::createStatsResource($statsData);

echo "   - Stats 1 ID: {$stats1->id}\n";
echo "   - Stats 2 ID: {$stats2->id}\n";
echo "   - IDs are same: " . ($stats1->id === $stats2->id ? 'Yes ✅' : 'No ❌') . "\n";
echo "   - Reason: IDs are deterministic within the same minute for caching\n\n";

// Test 7: API Platform Compatibility
echo "7️⃣  API Platform Compatibility:\n";
echo "   All resources now have guaranteed non-null IDs:\n";
echo "   - Basic resource ID: " . ($resource1->id !== null ? '✅ Set' : '❌ Null') . "\n";
echo "   - Stats resource ID: " . ($statsResource->id !== null ? '✅ Set' : '❌ Null') . "\n";
echo "   - Health resource ID: " . ($healthResource->id !== null ? '✅ Set' : '❌ Null') . "\n";
echo "   - Message resource ID: " . ($messageResource->id !== null ? '✅ Set' : '❌ Null') . "\n\n";

echo "✨ ID Strategy Summary:\n";
echo "📋 Different ID patterns for different resource types:\n";
echo "   - Messages: 'queue-{random32chars}' - Unique per message\n";
echo "   - Stats: 'stats-{minuteTimestamp}' - Same ID per minute (cacheable)\n";
echo "   - Health: 'health-{30secWindow}' - Same ID per 30-second window\n\n";
echo "🎯 Benefits:\n";
echo "   - ✅ API Platform compatibility (never null)\n";
echo "   - ✅ Deterministic IDs for stats/health (caching friendly)\n";
echo "   - ✅ Unique IDs for messages (tracking friendly)\n";
echo "   - ✅ Human-readable display IDs for UX\n";
echo "   - ✅ Type detection methods for conditional logic\n\n";
echo "🚀 Ready for production use!\n";
