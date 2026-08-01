<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260801000700 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservation ADD COLUMN guest_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE reservation ADD COLUMN guest_phone VARCHAR(32) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__reservation AS SELECT id, seating_date, pax, amount_cents, status, stripe_session_id, guest_email, created_at FROM reservation');
        $this->addSql('DROP TABLE reservation');
        $this->addSql('CREATE TABLE reservation (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, seating_date DATE NOT NULL, pax INTEGER NOT NULL, amount_cents INTEGER NOT NULL, status VARCHAR(20) NOT NULL, stripe_session_id VARCHAR(255) DEFAULT NULL, guest_email VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL)');
        $this->addSql('INSERT INTO reservation (id, seating_date, pax, amount_cents, status, stripe_session_id, guest_email, created_at) SELECT id, seating_date, pax, amount_cents, status, stripe_session_id, guest_email, created_at FROM __temp__reservation');
        $this->addSql('DROP TABLE __temp__reservation');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_42C849551A314A57 ON reservation (stripe_session_id)');
    }
}
