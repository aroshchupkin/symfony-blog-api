<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250906143703 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "user" RENAME TO "users"');
        $this->addSql('ALTER TABLE "post" RENAME TO "posts"');
        $this->addSql('ALTER TABLE "comment" RENAME TO "comments"');
        $this->addSql('ALTER SEQUENCE "user_id_seq" RENAME TO "users_id_seq"');
        $this->addSql('ALTER SEQUENCE "post_id_seq" RENAME TO "posts_id_seq"');
        $this->addSql('ALTER SEQUENCE "comment_id_seq" RENAME TO "comments_id_seq"');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "users" RENAME TO "user"');
        $this->addSql('ALTER TABLE "posts" RENAME TO "post"');
        $this->addSql('ALTER TABLE "comments" RENAME TO "comment"');
        $this->addSql('ALTER SEQUENCE "users_id_seq" RENAME TO "user_id_seq"');
        $this->addSql('ALTER SEQUENCE "posts_id_seq" RENAME TO "post_id_seq"');
        $this->addSql('ALTER SEQUENCE "comments_id_seq" RENAME TO "comment_id_seq"');
    }
}
