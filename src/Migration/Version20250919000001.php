<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration for QueuedMessage entity - Notification Tracking Transport
 */
final class Version20250919000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add notification_queued_messages table for custom messenger transport';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE notification_queued_messages (
                id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\',
                transport VARCHAR(50) NOT NULL,
                queue_name VARCHAR(100) NOT NULL,
                body LONGTEXT NOT NULL,
                headers JSON NOT NULL,
                created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                available_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                processed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                priority INT DEFAULT 0 NOT NULL,
                retry_count INT DEFAULT 0 NOT NULL,
                max_retries INT DEFAULT 3 NOT NULL,
                notification_provider VARCHAR(50) DEFAULT NULL,
                campaign_id VARCHAR(100) DEFAULT NULL,
                template_id VARCHAR(100) DEFAULT NULL,
                status VARCHAR(20) DEFAULT \'queued\' NOT NULL,
                error_message LONGTEXT DEFAULT NULL,
                processing_metadata JSON DEFAULT NULL,
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');

        // Add indexes for efficient querying
        $this->addSql('CREATE INDEX idx_transport_available ON notification_queued_messages (transport, available_at, delivered_at)');
        $this->addSql('CREATE INDEX idx_transport_queue_priority ON notification_queued_messages (transport, queue_name, priority)');
        $this->addSql('CREATE INDEX idx_status_created ON notification_queued_messages (status, created_at)');
        $this->addSql('CREATE INDEX idx_provider_campaign ON notification_queued_messages (notification_provider, campaign_id)');
        $this->addSql('CREATE INDEX idx_template_status ON notification_queued_messages (template_id, status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE notification_queued_messages');
    }
}
