<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260910000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Admin-editable menu: menu_item table seeded with the current six dishes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE menu_item (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, position INTEGER NOT NULL)');

        $dishes = [
            'Spiced Beef Suet, Grilled with Pineapple',
            'Hakka Pasta with Luicha Pesto',
            'Cabbage Wallet with Porky Paste',
            'Pasture-Raised Chicken, Tipsy Hakka Style',
            'Slow Roasted Pork Collar with Sun-Dried Bokchoy',
            'Sweet Potato Tongshui',
        ];
        foreach ($dishes as $i => $name) {
            $this->addSql('INSERT INTO menu_item (name, position) VALUES (?, ?)', [$name, $i + 1]);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE menu_item');
    }
}
