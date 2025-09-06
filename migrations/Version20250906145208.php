<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250906145208 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE comments DROP COLUMN author_name');
        $this->addSql('ALTER TABLE comments DROP COLUMN author_email');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE comments ADD COLUMN author_name VARCHAR(100) NOT NULL');
        $this->addSql('ALTER TABLE comments ADD COLUMN author_email VARCHAR(100) NOT NULL');
        $this->addSql('UPDATE comments SET author_name = (SELECT username FROM users WHERE users.id = comments.author_id)');
        $this->addSql('UPDATE comments SET author_email = (SELECT email FROM users WHERE users.id = comments.author_id)');
    }
}
