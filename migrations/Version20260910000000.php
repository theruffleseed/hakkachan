<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260910000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Admin time-off: closed seating dates';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE closed_date (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, seating_date DATE NOT NULL, created_at DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CLOSED_DATE_SEATING_DATE ON closed_date (seating_date)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE closed_date');
    }
}
