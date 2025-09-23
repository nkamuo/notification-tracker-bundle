<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to ensure enum fields are properly configured for status and direction fields.
 * This migration ensures database compatibility with the new enum types.
 */
final class Version20250923000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update status and direction fields to support enum values and ensure proper constraints';
    }

    public function up(Schema $schema): void
    {
        // Notification table status and direction constraints
        $this->addSql("
            ALTER TABLE notification_tracker_notifications 
            ADD CONSTRAINT check_notification_status 
            CHECK (status IN ('draft', 'scheduled', 'queued', 'sending', 'sent', 'failed', 'cancelled'))
        ");

        $this->addSql("
            ALTER TABLE notification_tracker_notifications 
            ADD CONSTRAINT check_notification_direction 
            CHECK (direction IN ('inbound', 'outbound', 'draft'))
        ");

        // Message table status and direction constraints
        $this->addSql("
            ALTER TABLE notification_tracker_messages 
            ADD CONSTRAINT check_message_status 
            CHECK (status IN ('pending', 'queued', 'sending', 'sent', 'delivered', 'failed', 'bounced', 'cancelled', 'retrying'))
        ");

        $this->addSql("
            ALTER TABLE notification_tracker_messages 
            ADD CONSTRAINT check_message_direction 
            CHECK (direction IN ('inbound', 'outbound', 'draft'))
        ");

        // Update any invalid status/direction values to valid ones
        $this->addSql("UPDATE notification_tracker_notifications SET status = 'draft' WHERE status NOT IN ('draft', 'scheduled', 'queued', 'sending', 'sent', 'failed', 'cancelled')");
        $this->addSql("UPDATE notification_tracker_notifications SET direction = 'draft' WHERE direction NOT IN ('inbound', 'outbound', 'draft')");
        
        $this->addSql("UPDATE notification_tracker_messages SET status = 'pending' WHERE status NOT IN ('pending', 'queued', 'sending', 'sent', 'delivered', 'failed', 'bounced', 'cancelled', 'retrying')");
        $this->addSql("UPDATE notification_tracker_messages SET direction = 'outbound' WHERE direction NOT IN ('inbound', 'outbound', 'draft')");

        // Draft notifications should have draft direction
        $this->addSql("UPDATE notification_tracker_notifications SET direction = 'draft' WHERE status = 'draft'");
    }

    public function down(Schema $schema): void
    {
        // Remove constraints
        $this->addSql('ALTER TABLE notification_tracker_notifications DROP CONSTRAINT IF EXISTS check_notification_status');
        $this->addSql('ALTER TABLE notification_tracker_notifications DROP CONSTRAINT IF EXISTS check_notification_direction');
        $this->addSql('ALTER TABLE notification_tracker_messages DROP CONSTRAINT IF EXISTS check_message_status');
        $this->addSql('ALTER TABLE notification_tracker_messages DROP CONSTRAINT IF EXISTS check_message_direction');
    }
}
