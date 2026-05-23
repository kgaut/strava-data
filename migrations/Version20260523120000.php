<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260523120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema: athlete and activity tables, with PostGIS geography points and indexes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS postgis');

        $this->addSql(<<<'SQL'
            CREATE TABLE athlete (
                id BIGINT NOT NULL,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                profile_picture_url VARCHAR(500) DEFAULT NULL,
                access_token TEXT NOT NULL,
                refresh_token TEXT NOT NULL,
                token_expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                last_synced_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY(id)
            )
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE activity (
                id BIGINT NOT NULL,
                athlete_id BIGINT NOT NULL,
                name VARCHAR(255) NOT NULL,
                type VARCHAR(50) NOT NULL,
                sport_type VARCHAR(50) NOT NULL,
                distance DOUBLE PRECISION NOT NULL,
                moving_time INT NOT NULL,
                elapsed_time INT NOT NULL,
                total_elevation_gain DOUBLE PRECISION NOT NULL,
                start_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                start_date_local TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                timezone VARCHAR(100) DEFAULT NULL,
                average_speed DOUBLE PRECISION DEFAULT NULL,
                max_speed DOUBLE PRECISION DEFAULT NULL,
                average_heartrate DOUBLE PRECISION DEFAULT NULL,
                max_heartrate DOUBLE PRECISION DEFAULT NULL,
                has_heartrate BOOLEAN NOT NULL,
                kudos_count INT NOT NULL,
                gear_id VARCHAR(100) DEFAULT NULL,
                trainer BOOLEAN NOT NULL,
                commute BOOLEAN NOT NULL,
                manual BOOLEAN NOT NULL,
                private BOOLEAN NOT NULL,
                summary_polyline TEXT DEFAULT NULL,
                start_latlng GEOGRAPHY(POINT, 4326) DEFAULT NULL,
                end_latlng GEOGRAPHY(POINT, 4326) DEFAULT NULL,
                imported_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);

        $this->addSql('CREATE INDEX activity_athlete_idx ON activity (athlete_id)');
        $this->addSql('CREATE INDEX activity_start_date_idx ON activity (start_date)');
        $this->addSql('CREATE INDEX activity_sport_type_idx ON activity (sport_type)');
        $this->addSql('CREATE INDEX activity_start_latlng_gist ON activity USING GIST (start_latlng)');

        $this->addSql(<<<'SQL'
            ALTER TABLE activity
            ADD CONSTRAINT FK_activity_athlete
            FOREIGN KEY (athlete_id) REFERENCES athlete (id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS activity');
        $this->addSql('DROP TABLE IF EXISTS athlete');
    }
}
