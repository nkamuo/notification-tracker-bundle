<?php

declare(strict_types=1);

/**
 * Example: Unified Tracking with Auto-Notification Creation
 * 
 * This example demonstrates how the notification tracker bundle now provides
 * unified tracking by automatically creating Notification entities as parent
 * containers for all messages, ensuring you always have a single entry point.
 */

use Nkamuo\NotificationTrackerBundle\Service\MessageTracker;
use Nkamuo\NotificationTrackerBundle\Entity\Notification;
use Nkamuo\NotificationTrackerBundle\Repository\NotificationRepository;
use Symfony\Component\Notifier\Message\EmailMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Mime\Email;

// Assume you have these services injected in your controller/service
/** @var MessageTracker $messageTracker */

/**
 * BEFORE v0.1.15: Direct channel usage created orphaned messages
 * 
 * $emailMessage = $messageTracker->trackEmail($email);
 * // Result: Message created without parent Notification ❌
 * // You'd need to manually create notifications for unified tracking
 */

/**
 * AFTER v0.1.15: Automatic unified tracking
 * 
 * When you track messages without providing a Notification entity,
 * the system automatically creates one for you!
 */

// Example 1: Direct email tracking with auto-notification
$email = (new Email())
    ->from('noreply@example.com')
    ->to('user@example.com')
    ->subject('Welcome to our service!')
    ->text('Thank you for signing up!');

$emailMessage = $messageTracker->trackEmail($email, 'smtp');
// ✅ Auto-creates: Notification with subject "[Email] Welcome to our service!"
// ✅ Links: EmailMessage → Notification (unified tracking)
// ✅ Access: $emailMessage->getNotification() returns the auto-created notification

// Example 2: Direct SMS tracking with auto-notification  
$sms = new SmsMessage('+1234567890', 'Your verification code: 123456');
$smsMessage = $messageTracker->trackSms($sms, 'twilio');
// ✅ Auto-creates: Notification with subject "[Sms] Your verification code: 123456"
// ✅ Links: SmsMessage → Notification (unified tracking)

// Example 3: Direct chat tracking with auto-notification
$chat = new ChatMessage('Meeting starts in 10 minutes!');
$slackMessage = $messageTracker->trackChat($chat, 'slack', 'slack_webhook');
// ✅ Auto-creates: Notification with subject "[Slack] Meeting starts in 10 minutes!"
// ✅ Links: SlackMessage → Notification (unified tracking)

/**
 * UNIFIED ACCESS: Now you can always use Notification as single entry point
 */

// Get all notifications (your single source of truth)
/** @var NotificationRepository $notificationRepo */
$allNotifications = $notificationRepo->findAll();

foreach ($allNotifications as $notification) {
    echo "Notification: {$notification->getSubject()}\n";
    echo "Type: {$notification->getType()}\n";
    echo "Channels: " . implode(', ', $notification->getChannels()) . "\n";
    
    // Access all messages under this notification
    foreach ($notification->getMessages() as $message) {
        echo "  - Message: {$message->getId()} ({$message->getMessageType()})\n";
        echo "    Status: {$message->getStatus()}\n";
        echo "    Recipients: {$message->getRecipients()->count()}\n";
    }
    echo "\n";
}

/**
 * BACKWARD COMPATIBILITY: Explicit notifications still work
 */

// You can still provide your own notification if needed
$customNotification = new Notification();
$customNotification->setType('user_onboarding');
$customNotification->setSubject('User Onboarding Campaign');
$customNotification->setChannels(['email', 'sms']);

// This will use your provided notification (no auto-creation)
$emailMessage = $messageTracker->trackEmail($email, 'smtp', $customNotification);
$smsMessage = $messageTracker->trackSms($sms, 'twilio', $customNotification);

// Both messages now belong to the same custom notification
echo "Messages under custom notification: " . $customNotification->getMessages()->count() . "\n";

/**
 * ANALYTICS: Query by notification for unified metrics
 */
$notifications = $notificationRepo->findByDateRange(
    new \DateTimeImmutable('-30 days'),
    new \DateTimeImmutable()
);

foreach ($notifications as $notification) {
    $totalMessages = $notification->getMessages()->count();
    $delivered = 0;
    $opened = 0;
    $clicked = 0;
    
    foreach ($notification->getMessages() as $message) {
        if ($message->getStatus() === 'delivered') $delivered++;
        
        foreach ($message->getRecipients() as $recipient) {
            if ($recipient->getOpenedAt()) $opened++;
            if ($recipient->getClickedAt()) $clicked++;
        }
    }
    
    echo "Campaign: {$notification->getSubject()}\n";
    echo "  Total Messages: $totalMessages\n";
    echo "  Delivered: $delivered\n";
    echo "  Opened: $opened\n";
    echo "  Clicked: $clicked\n";
    echo "  Open Rate: " . ($delivered > 0 ? round(($opened / $delivered) * 100, 2) : 0) . "%\n";
    echo "  Click Rate: " . ($opened > 0 ? round(($clicked / $opened) * 100, 2) : 0) . "%\n\n";
}

/**
 * KEY BENEFITS:
 * 
 * ✅ Single Entry Point: Always use Notification class for unified access
 * ✅ No Orphaned Messages: Every message has a parent notification
 * ✅ Backward Compatible: Existing code continues to work unchanged
 * ✅ Auto-Generated Intelligence: Smart subject generation based on content
 * ✅ Unified Analytics: Query notifications for cross-channel campaign metrics
 * ✅ Simplified Architecture: One place to find all your messaging data
 */
