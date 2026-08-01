<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260729133516 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE reservation (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, seating_date DATE NOT NULL, pax INTEGER NOT NULL, amount_cents INTEGER NOT NULL, status VARCHAR(20) NOT NULL, stripe_session_id VARCHAR(255) DEFAULT NULL, guest_email VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_42C849551A314A57 ON reservation (stripe_session_id)');
        $this->addSql('CREATE TABLE messenger_messages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, body CLOB NOT NULL, headers CLOB NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL)');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE reservation');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
