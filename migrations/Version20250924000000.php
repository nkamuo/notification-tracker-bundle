<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to create the proper notification tracker table structure with inheritance support
 */
final class Version20250924000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create notification tracker tables with proper inheritance structure and discriminator columns';
    }

    public function up(Schema $schema): void
    {
        // Create notifications table
        $this->addSql('CREATE TABLE notification_tracker_notifications (
            id VARCHAR(26) NOT NULL PRIMARY KEY,
            status VARCHAR(32) NOT NULL DEFAULT "draft",
            direction VARCHAR(32) NOT NULL DEFAULT "outbound",
            label VARCHAR(255) DEFAULT NULL,
            priority INT NOT NULL DEFAULT 0,
            subject VARCHAR(500) DEFAULT NULL,
            content TEXT DEFAULT NULL,
            sender VARCHAR(255) DEFAULT NULL,
            recipients JSON NOT NULL,
            metadata JSON NOT NULL,
            created_at TIMESTAMP NOT NULL,
            scheduled_at TIMESTAMP DEFAULT NULL,
            sent_at TIMESTAMP DEFAULT NULL,
            INDEX idx_nt_notification_status (status),
            INDEX idx_nt_notification_direction (direction),
            INDEX idx_nt_notification_scheduled_at (scheduled_at),
            INDEX idx_nt_notification_sent_at (sent_at)
        )');

        // Create base messages table with discriminator column for inheritance
        $this->addSql('CREATE TABLE notification_tracker_messages (
            id VARCHAR(26) NOT NULL PRIMARY KEY,
            type VARCHAR(100) NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT "pending",
            direction VARCHAR(32) NOT NULL DEFAULT "outbound",
            transport_name VARCHAR(100) DEFAULT NULL,
            transport_dsn TEXT DEFAULT NULL,
            metadata JSON NOT NULL,
            messenger_stamp_id VARCHAR(36) DEFAULT NULL,
            content_fingerprint VARCHAR(64) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL,
            updated_at TIMESTAMP DEFAULT NULL,
            scheduled_at TIMESTAMP DEFAULT NULL,
            sent_at TIMESTAMP DEFAULT NULL,
            has_schedule_override BOOLEAN NOT NULL DEFAULT FALSE,
            retry_count INT NOT NULL DEFAULT 0,
            failure_reason TEXT DEFAULT NULL,
            notification_id VARCHAR(26) DEFAULT NULL,
            template_id VARCHAR(26) DEFAULT NULL,
            INDEX idx_nt_message_status (status),
            INDEX idx_nt_message_sent_at (sent_at),
            INDEX idx_nt_message_notification (notification_id),
            INDEX idx_nt_message_stamp_id (messenger_stamp_id),
            INDEX idx_nt_message_fingerprint (content_fingerprint),
            INDEX idx_nt_message_type (type),
            FOREIGN KEY (notification_id) REFERENCES notification_tracker_notifications(id) ON DELETE SET NULL
        )');

        // Create inherited tables for different message types
        
        // Email messages
        $this->addSql('CREATE TABLE notification_tracker_email_messages (
            id VARCHAR(26) NOT NULL PRIMARY KEY,
            subject VARCHAR(500) DEFAULT NULL,
            html_content LONGTEXT DEFAULT NULL,
            text_content LONGTEXT DEFAULT NULL,
            from_email VARCHAR(255) DEFAULT NULL,
            from_name VARCHAR(255) DEFAULT NULL,
            to_email VARCHAR(255) DEFAULT NULL,
            to_name VARCHAR(255) DEFAULT NULL,
            cc_recipients JSON DEFAULT NULL,
            bcc_recipients JSON DEFAULT NULL,
            reply_to VARCHAR(255) DEFAULT NULL,
            headers JSON DEFAULT NULL,
            attachments JSON DEFAULT NULL,
            FOREIGN KEY (id) REFERENCES notification_tracker_messages(id) ON DELETE CASCADE
        )');

        // SMS messages
        $this->addSql('CREATE TABLE notification_tracker_sms_messages (
            id VARCHAR(26) NOT NULL PRIMARY KEY,
            phone_number VARCHAR(20) NOT NULL,
            content TEXT NOT NULL,
            from_number VARCHAR(20) DEFAULT NULL,
            FOREIGN KEY (id) REFERENCES notification_tracker_messages(id) ON DELETE CASCADE
        )');

        // Slack messages
        $this->addSql('CREATE TABLE notification_tracker_slack_messages (
            id VARCHAR(26) NOT NULL PRIMARY KEY,
            channel VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            username VARCHAR(255) DEFAULT NULL,
            icon_emoji VARCHAR(100) DEFAULT NULL,
            icon_url VARCHAR(500) DEFAULT NULL,
            attachments JSON DEFAULT NULL,
            blocks JSON DEFAULT NULL,
            FOREIGN KEY (id) REFERENCES notification_tracker_messages(id) ON DELETE CASCADE
        )');

        // Telegram messages
        $this->addSql('CREATE TABLE notification_tracker_telegram_messages (
            id VARCHAR(26) NOT NULL PRIMARY KEY,
            chat_id VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            parse_mode VARCHAR(20) DEFAULT NULL,
            disable_web_page_preview BOOLEAN DEFAULT FALSE,
            disable_notification BOOLEAN DEFAULT FALSE,
            FOREIGN KEY (id) REFERENCES notification_tracker_messages(id) ON DELETE CASCADE
        )');

        // Push notification messages
        $this->addSql('CREATE TABLE notification_tracker_push_messages (
            id VARCHAR(26) NOT NULL PRIMARY KEY,
            device_token VARCHAR(500) NOT NULL,
            title VARCHAR(255) DEFAULT NULL,
            body TEXT NOT NULL,
            data_payload JSON DEFAULT NULL,
            badge INT DEFAULT NULL,
            sound VARCHAR(100) DEFAULT NULL,
            FOREIGN KEY (id) REFERENCES notification_tracker_messages(id) ON DELETE CASCADE
        )');

        // Create labels table
        $this->addSql('CREATE TABLE notification_tracker_labels (
            id VARCHAR(26) NOT NULL PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            color VARCHAR(7) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            created_at TIMESTAMP NOT NULL,
            INDEX idx_nt_label_name (name)
        )');

        // Create notification-label junction table
        $this->addSql('CREATE TABLE notification_tracker_notification_labels (
            notification_id VARCHAR(26) NOT NULL,
            label_id VARCHAR(26) NOT NULL,
            PRIMARY KEY (notification_id, label_id),
            FOREIGN KEY (notification_id) REFERENCES notification_tracker_notifications(id) ON DELETE CASCADE,
            FOREIGN KEY (label_id) REFERENCES notification_tracker_labels(id) ON DELETE CASCADE
        )');

        // Create message templates table
        $this->addSql('CREATE TABLE notification_tracker_message_templates (
            id VARCHAR(26) NOT NULL PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            type VARCHAR(50) NOT NULL,
            subject_template VARCHAR(500) DEFAULT NULL,
            content_template LONGTEXT NOT NULL,
            metadata JSON DEFAULT NULL,
            created_at TIMESTAMP NOT NULL,
            updated_at TIMESTAMP DEFAULT NULL,
            INDEX idx_nt_template_name (name),
            INDEX idx_nt_template_type (type)
        )');

        // Create webhooks table
        $this->addSql('CREATE TABLE notification_tracker_webhooks (
            id VARCHAR(26) NOT NULL PRIMARY KEY,
            event_type VARCHAR(100) NOT NULL,
            url VARCHAR(500) NOT NULL,
            secret VARCHAR(255) DEFAULT NULL,
            is_active BOOLEAN NOT NULL DEFAULT TRUE,
            headers JSON DEFAULT NULL,
            created_at TIMESTAMP NOT NULL,
            last_triggered_at TIMESTAMP DEFAULT NULL,
            INDEX idx_nt_webhook_event (event_type),
            INDEX idx_nt_webhook_active (is_active)
        )');

        // Create engagement tracking table
        $this->addSql('CREATE TABLE notification_tracker_engagement_events (
            id VARCHAR(26) NOT NULL PRIMARY KEY,
            message_id VARCHAR(26) NOT NULL,
            event_type VARCHAR(50) NOT NULL,
            user_agent TEXT DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            metadata JSON DEFAULT NULL,
            created_at TIMESTAMP NOT NULL,
            INDEX idx_nt_engagement_message (message_id),
            INDEX idx_nt_engagement_type (event_type),
            INDEX idx_nt_engagement_created (created_at),
            FOREIGN KEY (message_id) REFERENCES notification_tracker_messages(id) ON DELETE CASCADE
        )');
    }

    public function down(Schema $schema): void
    {
        // Drop all tables in reverse order due to foreign key constraints
        $this->addSql('DROP TABLE notification_tracker_engagement_events');
        $this->addSql('DROP TABLE notification_tracker_webhooks');
        $this->addSql('DROP TABLE notification_tracker_message_templates');
        $this->addSql('DROP TABLE notification_tracker_notification_labels');
        $this->addSql('DROP TABLE notification_tracker_labels');
        $this->addSql('DROP TABLE notification_tracker_push_messages');
        $this->addSql('DROP TABLE notification_tracker_telegram_messages');
        $this->addSql('DROP TABLE notification_tracker_slack_messages');
        $this->addSql('DROP TABLE notification_tracker_sms_messages');
        $this->addSql('DROP TABLE notification_tracker_email_messages');
        $this->addSql('DROP TABLE notification_tracker_messages');
        $this->addSql('DROP TABLE notification_tracker_notifications');
    }
}
