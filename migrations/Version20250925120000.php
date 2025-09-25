<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to update notification direction logic - remove DRAFT from direction enum
 * Direction now only handles INBOUND/OUTBOUND, status handles draft state
 */
final class Version20250925120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update notification direction to only use inbound/outbound, draft moved to status';
    }

    public function up(Schema $schema): void
    {
        // Update all existing notifications with direction=draft to direction=outbound and status=draft
        $this->addSql("
            UPDATE notification_tracker_notifications 
            SET direction = 'outbound' 
            WHERE direction = 'draft'
        ");

        // Update all existing messages with direction=draft to direction=outbound 
        $this->addSql("
            UPDATE notification_tracker_messages 
            SET direction = 'outbound' 
            WHERE direction = 'draft'
        ");
    }

    public function down(Schema $schema): void
    {
        // Reverse migration: set direction back to draft for notifications with status=draft
        $this->addSql("
            UPDATE notification_tracker_notifications 
            SET direction = 'draft' 
            WHERE status = 'draft' AND direction = 'outbound'
        ");

        // For messages, we can't easily determine which should be draft, so we leave them as outbound
        // This is because messages don't have a direct status field that maps to draft
    }
}
