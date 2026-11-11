<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261107090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move Bloom Box products to Others, then rename the Bloom Box category to Basket Bouquet (left empty).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE product SET category_id = (SELECT id FROM category WHERE slug = 'others') WHERE category_id = (SELECT id FROM category WHERE slug = 'bloom-box')");
        $this->addSql("UPDATE category SET categoryname = 'Basket Bouquet', slug = 'basket-bouquet', description = 'Basket Bouquet arrangements' WHERE slug = 'bloom-box'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE category SET categoryname = 'Bloom Box', slug = 'bloom-box', description = 'Bloom Box arrangements' WHERE slug = 'basket-bouquet'");
        // The moved products are not tracked, so only the original Bloom Box product is restored.
        $this->addSql("UPDATE product SET category_id = (SELECT id FROM category WHERE slug = 'bloom-box') WHERE name = 'Bloom Arrangement No. 10'");
    }
}
