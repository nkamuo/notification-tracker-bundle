<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to add messenger stamp ID and content fingerprint fields to the Message entity
 * for proper retry tracking instead of duplicating messages.
 */
final class Version20241222190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add messenger_stamp_id and content_fingerprint to communication_messages table for retry tracking';
    }

    public function up(Schema $schema): void
    {
        // Add messenger_stamp_id field for tracking message identity across retries
        $this->addSql('ALTER TABLE communication_messages ADD messenger_stamp_id VARCHAR(255) DEFAULT NULL');
        
        // Add content_fingerprint field for analytics purposes
        $this->addSql('ALTER TABLE communication_messages ADD content_fingerprint VARCHAR(255) DEFAULT NULL');
        
        // Add indexes for performance
        $this->addSql('CREATE INDEX IDX_messenger_stamp_id ON communication_messages (messenger_stamp_id)');
        $this->addSql('CREATE INDEX IDX_content_fingerprint ON communication_messages (content_fingerprint)');
    }

    public function down(Schema $schema): void
    {
        // Remove indexes first
        $this->addSql('DROP INDEX IDX_messenger_stamp_id ON communication_messages');
        $this->addSql('DROP INDEX IDX_content_fingerprint ON communication_messages');
        
        // Remove the columns
        $this->addSql('ALTER TABLE communication_messages DROP messenger_stamp_id');
        $this->addSql('ALTER TABLE communication_messages DROP content_fingerprint');
    }
}
