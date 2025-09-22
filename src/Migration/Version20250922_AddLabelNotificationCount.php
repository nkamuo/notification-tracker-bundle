<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add notification_count field to labels table with index
 */
final class Version20250922_AddLabelNotificationCount extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add notification_count field to nt_label table with index for performance';
    }

    public function up(Schema $schema): void
    {
        // Add the notification_count column
        $this->addSql('ALTER TABLE nt_label ADD COLUMN notification_count INTEGER DEFAULT 0 NOT NULL');
        
        // Add index for the new column
        $this->addSql('CREATE INDEX idx_nt_label_notification_count ON nt_label (notification_count)');
        
        // Update existing records to have correct counts
        $this->addSql('
            UPDATE nt_label 
            SET notification_count = (
                SELECT COUNT(*) 
                FROM notification_tracker_notification_label 
                WHERE notification_tracker_notification_label.label_id = nt_label.id
            )
        ');
    }

    public function down(Schema $schema): void
    {
        // Drop the index first
        $this->addSql('DROP INDEX idx_nt_label_notification_count');
        
        // Drop the column
        $this->addSql('ALTER TABLE nt_label DROP COLUMN notification_count');
    }
}
