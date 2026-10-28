-- Apply once to an existing database after backing it up.
-- Fresh databases created from schema.sql already have these columns.

ALTER TABLE nutrient_profiles
    ADD COLUMN preparation_state VARCHAR(80) NOT NULL DEFAULT 'not stated'
        AFTER vitamin_b12_ug;

ALTER TABLE prices
    ADD COLUMN source_reference VARCHAR(500) DEFAULT NULL
        AFTER source;

DELETE FROM nutrient_profiles
WHERE source_note LIKE 'Approximate reference value%';

DELETE FROM prices
WHERE market_name LIKE '%(sample)%';

CREATE OR REPLACE VIEW latest_prices AS
SELECT p.food_id, p.price_rwf, p.market_name, p.district, p.recorded_on,
       p.source, p.source_reference
FROM prices p
WHERE p.id = (
    SELECT p2.id
    FROM prices p2
    WHERE p2.food_id = p.food_id
    ORDER BY p2.recorded_on DESC, p2.id DESC
    LIMIT 1
);

CREATE TABLE agricultural_data_sources (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dataset_title       VARCHAR(255) NOT NULL,
    publisher           VARCHAR(160) NOT NULL,
    release_year        SMALLINT UNSIGNED NOT NULL,
    citation            VARCHAR(500) NOT NULL,
    reference_url       VARCHAR(500) NOT NULL,
    reuse_terms         VARCHAR(500) NOT NULL,
    imported_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_agricultural_dataset (dataset_title, publisher, release_year)
) ENGINE=InnoDB;

CREATE TABLE district_crop_statistics (
    district_id             INT UNSIGNED NOT NULL,
    crop_id                 INT UNSIGNED NOT NULL,
    season                  CHAR(1) NOT NULL,
    survey_year             SMALLINT UNSIGNED NOT NULL,
    cultivated_area_ha      DECIMAL(12,3) NOT NULL,
    production_tonnes       DECIMAL(14,3) NOT NULL,
    data_source_id          INT UNSIGNED NOT NULL,
    imported_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (district_id, crop_id, season, survey_year),
    INDEX idx_crop_stats_source (data_source_id),
    CONSTRAINT chk_crop_stats_season CHECK (season IN ('A', 'B', 'C')),
    CONSTRAINT chk_crop_stats_area CHECK (cultivated_area_ha > 0),
    CONSTRAINT chk_crop_stats_production CHECK (production_tonnes >= 0),
    FOREIGN KEY (district_id) REFERENCES districts(id) ON DELETE CASCADE,
    FOREIGN KEY (crop_id) REFERENCES crops(id) ON DELETE CASCADE,
    FOREIGN KEY (data_source_id) REFERENCES agricultural_data_sources(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
