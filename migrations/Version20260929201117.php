<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260929201117 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Conversion des données existantes (format simple_array "a,b", ou NULL depuis le bug
        // corrigé par la migration précédente) vers un tableau JSON, jamais NULL.
        $this->addSql("ALTER TABLE books ALTER authors TYPE JSON USING to_json(COALESCE(string_to_array(authors, ','), ARRAY[]::text[]))");
        $this->addSql('ALTER TABLE books ALTER authors SET NOT NULL');
        $this->addSql("ALTER TABLE books ALTER genres TYPE JSON USING to_json(COALESCE(string_to_array(genres, ','), ARRAY[]::text[]))");
        $this->addSql('ALTER TABLE books ALTER genres SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE books ALTER authors TYPE TEXT USING array_to_string(ARRAY(SELECT json_array_elements_text(authors)), ',')");
        $this->addSql('ALTER TABLE books ALTER authors DROP NOT NULL');
        $this->addSql("ALTER TABLE books ALTER genres TYPE TEXT USING array_to_string(ARRAY(SELECT json_array_elements_text(genres)), ',')");
        $this->addSql('ALTER TABLE books ALTER genres DROP NOT NULL');
    }
}
