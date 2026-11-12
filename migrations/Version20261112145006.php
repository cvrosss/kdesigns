<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Two categories could share a name. Their slugs then collided on the already-unique
 * slug column and the save died with an uncaught constraint violation (HTTP 500).
 * The name is now unique in its own right, so the duplicate is caught as a form error.
 */
final class Version20261112145006 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make category names unique so duplicates are rejected as a validation error, not a 500';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CATEGORY_NAME ON category (categoryname)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_CATEGORY_NAME ON category');
    }
}
