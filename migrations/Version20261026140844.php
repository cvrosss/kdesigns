<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261026140844 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // "Processing" was replaced by "Successful" (pick-up) / "Delivered" (delivery).
        $this->addSql("UPDATE orders SET status = IF(fulfillment = 'delivery', 'delivered', 'successful') WHERE status = 'processing'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE orders SET status = 'processing' WHERE status = 'successful'");
    }
}
