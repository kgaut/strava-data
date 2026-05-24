<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260524100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop OAuth token columns from athlete (tokens now come from STRAVA_REFRESH_TOKEN env).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE athlete DROP COLUMN IF EXISTS access_token');
        $this->addSql('ALTER TABLE athlete DROP COLUMN IF EXISTS refresh_token');
        $this->addSql('ALTER TABLE athlete DROP COLUMN IF EXISTS token_expires_at');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE athlete ADD COLUMN access_token TEXT NOT NULL DEFAULT ''");
        $this->addSql("ALTER TABLE athlete ADD COLUMN refresh_token TEXT NOT NULL DEFAULT ''");
        $this->addSql('ALTER TABLE athlete ADD COLUMN token_expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT now()');
    }
}
