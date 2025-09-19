# Database Migrations

## Initial Migration

```php
<?php
// migrations/Version20240115000001.php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240115000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create communication tracking tables';
    }

    public function up(Schema $schema): void
    {
        // Notifications table
        $this->addSql('CREATE TABLE communication_notifications (
            id UUID NOT NULL,
            type VARCHAR(100) NOT NULL,
            importance VARCHAR(20) NOT NULL,
            channels JSON NOT NULL,
            context JSON NOT NULL,
            user_id UUID DEFAULT NULL,
            created_at TIMESTAMP NOT NULL,
            PRIMARY KEY(id)
        )');

        // Messages base table
        $this->addSql('CREATE TABLE communication_messages (
            id UUID NOT NULL,
            type VARCHAR(50) NOT NULL,
            status VARCHAR(50) NOT NULL,
            transport_name VARCHAR(100) DEFAULT NULL,
            transport_dsn TEXT DEFAULT NULL,
            metadata JSON NOT NULL,
            created_at TIMESTAMP NOT NULL,
            updated_at TIMESTAMP DEFAULT NULL,
            scheduled_at TIMESTAMP DEFAULT NULL,
            sent_at TIMESTAMP DEFAULT NULL,
            retry_count INT NOT NULL DEFAULT 0,
            failure_reason TEXT DEFAULT NULL,
            notification_id UUID DEFAULT NULL,
            template_id UUID DEFAULT NULL,
            PRIMARY KEY(id),
            CONSTRAINT FK_MSG_NOTIFICATION FOREIGN KEY (notification_id) 
                REFERENCES communication_notifications (id) ON DELETE SET NULL
        )');

        $this->addSql('CREATE INDEX idx_message_status ON communication_messages (status)');
        $this->addSql('CREATE INDEX idx_message_sent_at ON communication_messages (sent_at)');
        $this->addSql('CREATE INDEX idx_message_notification ON communication_messages (notification_id)');

        // Email messages table
        $this->addSql('CREATE TABLE communication_email_messages (
            id UUID NOT NULL,
            subject VARCHAR(255) NOT NULL,
            from_email VARCHAR(255) NOT NULL,
            from_name VARCHAR(255) DEFAULT NULL,
            reply_to VARCHAR(255) DEFAULT NULL,
            headers JSON NOT NULL,
            message_id VARCHAR(255) DEFAULT NULL,
            track_opens BOOLEAN NOT NULL DEFAULT TRUE,
            track_clicks BOOLEAN NOT NULL DEFAULT TRUE,
            PRIMARY KEY(id),
            CONSTRAINT FK_EMAIL_MSG FOREIGN KEY (id) 
                REFERENCES communication_messages (id) ON DELETE CASCADE
        )');

        // SMS messages table
        $this->addSql('CREATE TABLE communication_sms_messages (
            id UUID NOT NULL,
            from_number VARCHAR(50) DEFAULT NULL,
            provider_message_id VARCHAR(255) DEFAULT NULL,
            segments_count INT DEFAULT 1,
            cost DECIMAL(10, 4) DEFAULT NULL,
            PRIMARY KEY(id),
            CONSTRAINT FK_SMS_MSG FOREIGN KEY (id) 
                REFERENCES communication_messages (id) ON DELETE CASCADE
        )');

        // Slack messages table
        $this->addSql('CREATE TABLE communication_slack_messages (
            id UUID NOT NULL,
            channel VARCHAR(100) NOT NULL,
            thread_ts VARCHAR(50) DEFAULT NULL,
            PRIMARY KEY(id),
            CONSTRAINT FK_SLACK_MSG FOREIGN KEY (id) 
                REFERENCES communication_messages (id) ON DELETE CASCADE
        )');

        // Telegram messages table
        $this->addSql('CREATE TABLE communication_telegram_messages (
            id UUID NOT NULL,
            chat_id VARCHAR(100) NOT NULL,
            message_thread_id INT DEFAULT NULL,
            PRIMARY KEY(id),
            CONSTRAINT FK_TELEGRAM_MSG FOREIGN KEY (id) 
                REFERENCES communication_messages (id) ON DELETE CASCADE
        )');

        // Message content table
        $this->addSql('CREATE TABLE communication_message_content (
            id UUID NOT NULL,
            message_id UUID NOT NULL,
            content_type VARCHAR(50) NOT NULL,
            body_text TEXT DEFAULT NULL,
            body_html TEXT DEFAULT NULL,
            structured_data JSON DEFAULT NULL,
            PRIMARY KEY(id),
            CONSTRAINT FK_CONTENT_MSG FOREIGN KEY (message_id) 
                REFERENCES communication_messages (id) ON DELETE CASCADE
        )');

        // Message recipients table
        $this->addSql('CREATE TABLE communication_message_recipients (
            id UUID NOT NULL,
            message_id UUID NOT NULL,
            type VARCHAR(20) NOT NULL,
            address VARCHAR(255) NOT NULL,
            name VARCHAR(255) DEFAULT NULL,
            status VARCHAR(50) NOT NULL,
            delivered_at TIMESTAMP DEFAULT NULL,
            opened_at TIMESTAMP DEFAULT NULL,
            clicked_at TIMESTAMP DEFAULT NULL,
            bounced_at TIMESTAMP DEFAULT NULL,
            metadata JSON NOT NULL,
            PRIMARY KEY(id),
            CONSTRAINT FK_RECIPIENT_MSG FOREIGN KEY (message_id) 
                REFERENCES communication_messages (id) ON DELETE CASCADE
        )');

        $this->addSql('CREATE INDEX idx_recipient_message ON communication_message_recipients (message_id)');

        // Message events table
        $this->addSql('CREATE TABLE communication_message_events (
            id UUID NOT NULL,
            message_id UUID NOT NULL,
            recipient_id UUID DEFAULT NULL,
            event_type VARCHAR(50) NOT NULL,
            event_data JSON NOT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent TEXT DEFAULT NULL,
            occurred_at TIMESTAMP NOT NULL,
            webhook_payload_id UUID DEFAULT NULL,
            PRIMARY KEY(id),
            CONSTRAINT FK_EVENT_MSG FOREIGN KEY (message_id) 
                REFERENCES communication_messages (id) ON DELETE CASCADE,
            CONSTRAINT FK_EVENT_RECIPIENT FOREIGN KEY (recipient_id) 
                REFERENCES communication_message_recipients (id) ON DELETE SET NULL
        )');

        $this->addSql('CREATE INDEX idx_event_message ON communication_message_events (message_id)');
        $this->addSql('CREATE INDEX idx_event_type_date ON communication_message_events (event_type, occurred_at)');

        // Webhook payloads table
        $this->addSql('CREATE TABLE communication_webhook_payloads (
            id UUID NOT NULL,
            provider VARCHAR(50) NOT NULL,
            event_type VARCHAR(50) DEFAULT NULL,
            raw_payload JSON NOT NULL,
            signature VARCHAR(255) DEFAULT NULL,
            processed BOOLEAN NOT NULL DEFAULT FALSE,
            received_at TIMESTAMP NOT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE INDEX idx_webhook_provider_processed ON communication_webhook_payloads (provider, processed)');

        // Message templates table
        $this->addSql('CREATE TABLE communication_message_templates (
            id UUID NOT NULL,
            name VARCHAR(100) NOT NULL,
            type VARCHAR(50) NOT NULL,
            variables JSON NOT NULL,
            content TEXT NOT NULL,
            active BOOLEAN NOT NULL DEFAULT TRUE,
            created_at TIMESTAMP NOT NULL,
            updated_at TIMESTAMP DEFAULT NULL,
            PRIMARY KEY(id)
        )');

        // Message attachments table
        $this->addSql('CREATE TABLE communication_message_attachments (
            id UUID NOT NULL,
            message_id UUID NOT NULL,
            filename VARCHAR(255) NOT NULL,
            content_type VARCHAR(100) NOT NULL,
            size INT NOT NULL,
            path VARCHAR(500) DEFAULT NULL,
            content TEXT DEFAULT NULL,
            PRIMARY KEY(id),