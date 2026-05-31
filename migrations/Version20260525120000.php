<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Wandrer-style road coverage: admin_area, road_segment, road_visit tables with GIST indexes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE admin_area (
                id BIGINT NOT NULL,
                admin_level SMALLINT NOT NULL,
                name VARCHAR(255) NOT NULL,
                code VARCHAR(20) DEFAULT NULL,
                geometry GEOGRAPHY(MULTIPOLYGON, 4326) NOT NULL,
                imported_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX admin_area_level_idx ON admin_area (admin_level)');
        $this->addSql('CREATE INDEX admin_area_geometry_gist ON admin_area USING GIST (geometry)');

        $this->addSql(<<<'SQL'
            CREATE TABLE road_segment (
                id BIGINT NOT NULL,
                highway VARCHAR(50) NOT NULL,
                name VARCHAR(255) DEFAULT NULL,
                surface VARCHAR(50) DEFAULT NULL,
                geometry GEOGRAPHY(LINESTRING, 4326) NOT NULL,
                length_m DOUBLE PRECISION NOT NULL,
                commune_id BIGINT DEFAULT NULL,
                department_id BIGINT DEFAULT NULL,
                imported_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX road_segment_highway_idx ON road_segment (highway)');
        $this->addSql('CREATE INDEX road_segment_commune_idx ON road_segment (commune_id)');
        $this->addSql('CREATE INDEX road_segment_department_idx ON road_segment (department_id)');
        $this->addSql('CREATE INDEX road_segment_geometry_gist ON road_segment USING GIST (geometry)');
        $this->addSql(<<<'SQL'
            ALTER TABLE road_segment
            ADD CONSTRAINT FK_road_segment_commune
            FOREIGN KEY (commune_id) REFERENCES admin_area (id) ON DELETE SET NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE road_segment
            ADD CONSTRAINT FK_road_segment_department
            FOREIGN KEY (department_id) REFERENCES admin_area (id) ON DELETE SET NULL
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE road_visit (
                road_segment_id BIGINT NOT NULL,
                first_activity_id BIGINT DEFAULT NULL,
                first_visited_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(road_segment_id)
            )
        SQL);
        $this->addSql('CREATE INDEX road_visit_first_activity_idx ON road_visit (first_activity_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE road_visit
            ADD CONSTRAINT FK_road_visit_segment
            FOREIGN KEY (road_segment_id) REFERENCES road_segment (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE road_visit
            ADD CONSTRAINT FK_road_visit_first_activity
            FOREIGN KEY (first_activity_id) REFERENCES activity (id) ON DELETE SET NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS road_visit');
        $this->addSql('DROP TABLE IF EXISTS road_segment');
        $this->addSql('DROP TABLE IF EXISTS admin_area');
    }
}
