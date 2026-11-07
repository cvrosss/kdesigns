<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261025084827 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE feedback (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, message VARCHAR(1000) NOT NULL, status VARCHAR(10) NOT NULL, created_at DATETIME NOT NULL, user_id INT DEFAULT NULL, INDEX idx_feedback_status_created (status, created_at), INDEX IDX_D2294458A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE orders (id INT AUTO_INCREMENT NOT NULL, reference VARCHAR(20) NOT NULL, product_name VARCHAR(150) NOT NULL, unit_price INT UNSIGNED NOT NULL, quantity INT UNSIGNED DEFAULT 1 NOT NULL, product_image VARCHAR(255) NOT NULL, customer_name VARCHAR(150) NOT NULL, contact VARCHAR(50) NOT NULL, fulfillment VARCHAR(10) NOT NULL, date_needed DATE DEFAULT NULL, time_needed VARCHAR(5) DEFAULT NULL, payment_method VARCHAR(20) NOT NULL, delivery_receiver VARCHAR(150) DEFAULT NULL, delivery_contact VARCHAR(50) DEFAULT NULL, delivery_location LONGTEXT DEFAULT NULL, status VARCHAR(12) NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, product_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_E52FFDEEAEA34913 (reference), INDEX idx_orders_status (status), INDEX IDX_E52FFDEEA76ED395 (user_id), INDEX IDX_E52FFDEE4584665A (product_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE product (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(150) NOT NULL, description LONGTEXT NOT NULL, price INT UNSIGNED NOT NULL, stock INT UNSIGNED DEFAULT 0 NOT NULL, badge VARCHAR(30) DEFAULT NULL, image VARCHAR(255) NOT NULL, sort_order INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, category_id INT NOT NULL, UNIQUE INDEX UNIQ_D34A04AD5E237E06 (name), INDEX idx_product_sort (sort_order, id), INDEX IDX_D34A04AD12469DE2 (category_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE feedback ADD CONSTRAINT FK_D2294458A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_E52FFDEEA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_E52FFDEE4584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04AD12469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE RESTRICT');
        // Existing rows get a slug / timestamp before the columns become NOT NULL.
        $this->addSql('ALTER TABLE category ADD slug VARCHAR(100) DEFAULT NULL');
        $this->addSql("UPDATE category SET slug = CONCAT('category-', id) WHERE slug IS NULL");
        $this->addSql('ALTER TABLE category MODIFY slug VARCHAR(100) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_64C19C1989D9B62 ON category (slug)');
        $this->addSql('ALTER TABLE `user` ADD created_at DATETIME DEFAULT NULL');
        $this->addSql('UPDATE `user` SET created_at = NOW() WHERE created_at IS NULL');
        $this->addSql('ALTER TABLE `user` MODIFY created_at DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE feedback DROP FOREIGN KEY FK_D2294458A76ED395');
        $this->addSql('ALTER TABLE orders DROP FOREIGN KEY FK_E52FFDEEA76ED395');
        $this->addSql('ALTER TABLE orders DROP FOREIGN KEY FK_E52FFDEE4584665A');
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04AD12469DE2');
        $this->addSql('DROP TABLE feedback');
        $this->addSql('DROP TABLE orders');
        $this->addSql('DROP TABLE product');
        $this->addSql('DROP INDEX UNIQ_64C19C1989D9B62 ON category');
        $this->addSql('ALTER TABLE category DROP slug');
        $this->addSql('ALTER TABLE `user` DROP created_at');
    }
}
