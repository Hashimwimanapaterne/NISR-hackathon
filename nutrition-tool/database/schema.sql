-- ============================================================
-- Umurima Data — Nutrition-per-Cost Tool
-- Database schema (Phase 1 of the NISR Big Data Hackathon MVP)
-- ============================================================
-- Run with: mysql -u root -p < schema.sql

CREATE DATABASE IF NOT EXISTS umurima_nutrition
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE umurima_nutrition;

-- ------------------------------------------------------------
-- foods: one row per market food item
-- ------------------------------------------------------------
CREATE TABLE foods (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(120) NOT NULL,
    category        VARCHAR(60)  NOT NULL,   -- e.g. Grains, Tubers, Legumes, Vegetables, Animal Protein
    unit_label      VARCHAR(30)  NOT NULL DEFAULT 'kg',  -- unit the price refers to (kg, litre, piece)
    grams_per_unit  DECIMAL(8,2) NOT NULL DEFAULT 1000,  -- grams represented by one unit_label
                                                          -- (e.g. 1000 for 'kg'/'litre', ~200 for one
                                                          -- avocado 'piece', ~1650 for a 30-egg 'tray')
                                                          -- lets us convert any price into a cost-per-100g
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
                                  ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_food_name (name)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- nutrient_profiles: sourced nutrition content per 100g with
-- preparation state and source note.
-- ------------------------------------------------------------
CREATE TABLE nutrient_profiles (
    food_id         INT UNSIGNED PRIMARY KEY,
    -- Energy & macronutrients
    calories_kcal      DECIMAL(7,2) NOT NULL DEFAULT 0,
    protein_g          DECIMAL(7,2) NOT NULL DEFAULT 0,
    fat_g              DECIMAL(7,2) NOT NULL DEFAULT 0,
    carbohydrates_g    DECIMAL(7,2) NOT NULL DEFAULT 0,
    fiber_g            DECIMAL(7,2) NOT NULL DEFAULT 0,
    -- Vitamins & minerals most relevant to Rwanda's malnutrition/stunting priorities
    iron_mg            DECIMAL(7,2) NOT NULL DEFAULT 0,
    zinc_mg            DECIMAL(7,2) NOT NULL DEFAULT 0,
    calcium_mg         DECIMAL(7,2) NOT NULL DEFAULT 0,
    potassium_mg       DECIMAL(8,2) NOT NULL DEFAULT 0,
    vitamin_a_ug       DECIMAL(7,2) NOT NULL DEFAULT 0,
    vitamin_c_mg       DECIMAL(7,2) NOT NULL DEFAULT 0,
    folate_ug          DECIMAL(7,2) NOT NULL DEFAULT 0,
    vitamin_b12_ug     DECIMAL(7,2) NOT NULL DEFAULT 0,
    preparation_state VARCHAR(80) NOT NULL DEFAULT 'not stated',
    source_note     VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (food_id) REFERENCES foods(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- prices: current market price per food. Kept as its own table
-- (rather than a column on foods) so price history / e-Soko
-- imports can append rows later without changing the schema.
-- ------------------------------------------------------------
CREATE TABLE prices (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    food_id         INT UNSIGNED NOT NULL,
    price_rwf       DECIMAL(10,2) NOT NULL,     -- price per unit_label
    market_name     VARCHAR(120) DEFAULT NULL,  -- e.g. 'Kimironko', 'Nyabugogo'
    district        VARCHAR(80)  DEFAULT NULL,
    recorded_on     DATE NOT NULL,
    source          VARCHAR(60)  NOT NULL DEFAULT 'manual_observation',
    source_reference VARCHAR(500) DEFAULT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (food_id) REFERENCES foods(id) ON DELETE CASCADE,
    INDEX idx_food_recorded (food_id, recorded_on)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- admin_users: for the admin panel that manages foods/prices
-- ------------------------------------------------------------
CREATE TABLE admin_users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(60) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- districts: Rwanda district lookup for crop recommendations
-- ------------------------------------------------------------
CREATE TABLE districts (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(60) NOT NULL UNIQUE,
    province    VARCHAR(60) NOT NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- crops: crops linked to their existing food/nutrient records
-- ------------------------------------------------------------
CREATE TABLE crops (
    id                              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name                            VARCHAR(80) NOT NULL UNIQUE,
    food_id                         INT UNSIGNED NOT NULL,
    typical_yield_kg_per_hectare    DECIMAL(8,2) DEFAULT NULL,
    harvest_days_min                SMALLINT UNSIGNED DEFAULT NULL,
    harvest_days_max                SMALLINT UNSIGNED DEFAULT NULL,
    harvest_duration_note           VARCHAR(255) DEFAULT NULL,
    notes                           VARCHAR(255) DEFAULT NULL,
    rotation_guidance               VARCHAR(500) DEFAULT NULL,
    soil_stewardship_guidance       VARCHAR(600) DEFAULT NULL,
    CONSTRAINT chk_crop_harvest_days CHECK (
        (harvest_days_min IS NULL AND harvest_days_max IS NULL)
        OR (harvest_days_min IS NOT NULL AND harvest_days_max IS NOT NULL
            AND harvest_days_min > 0 AND harvest_days_max >= harvest_days_min)
    ),
    FOREIGN KEY (food_id) REFERENCES foods(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Agricultural source registry and aggregated district crop observations
-- ------------------------------------------------------------
CREATE TABLE agricultural_data_sources (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dataset_title       VARCHAR(255) NOT NULL,
    publisher           VARCHAR(160) NOT NULL,
    release_year        SMALLINT UNSIGNED NOT NULL,
    citation            VARCHAR(500) NOT NULL,
    reference_url       VARCHAR(500) NOT NULL,
    reuse_terms         VARCHAR(500) NOT NULL,
    is_active_for_scoring TINYINT(1) NOT NULL DEFAULT 0,
    imported_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_agricultural_dataset (dataset_title, publisher, release_year)
) ENGINE=InnoDB;

CREATE TABLE district_crop_statistics (
    district_id             INT UNSIGNED NOT NULL,
    crop_id                 INT UNSIGNED NOT NULL,
    season                  CHAR(1) NOT NULL,
    survey_year             SMALLINT UNSIGNED NOT NULL,
    cultivated_area_ha      DECIMAL(12,3) DEFAULT NULL,
    production_tonnes       DECIMAL(14,3) DEFAULT NULL,
    harvested_area_ha       DECIMAL(12,3) DEFAULT NULL,
    published_yield_kg_per_hectare DECIMAL(12,3) DEFAULT NULL,
    data_source_id          INT UNSIGNED NOT NULL,
    imported_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (district_id, crop_id, season, survey_year, data_source_id),
    INDEX idx_crop_stats_source (data_source_id),
    CONSTRAINT chk_crop_stats_season CHECK (season IN ('A', 'B', 'C')),
    CONSTRAINT chk_crop_stats_area CHECK (cultivated_area_ha IS NULL OR cultivated_area_ha > 0),
    CONSTRAINT chk_crop_stats_production CHECK (production_tonnes IS NULL OR production_tonnes >= 0),
    CONSTRAINT chk_crop_stats_harvested_area CHECK (harvested_area_ha IS NULL OR harvested_area_ha > 0),
    CONSTRAINT chk_crop_stats_published_yield CHECK (
        published_yield_kg_per_hectare IS NULL OR published_yield_kg_per_hectare >= 0
    ),
    FOREIGN KEY (district_id) REFERENCES districts(id) ON DELETE CASCADE,
    FOREIGN KEY (crop_id) REFERENCES crops(id) ON DELETE CASCADE,
    FOREIGN KEY (data_source_id) REFERENCES agricultural_data_sources(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE agricultural_survey_observations (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    data_source_id      INT UNSIGNED NOT NULL,
    record_key          CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    survey_year         SMALLINT UNSIGNED NOT NULL,
    season              CHAR(1) NOT NULL,
    table_number        SMALLINT UNSIGNED NOT NULL,
    table_title         VARCHAR(255) NOT NULL,
    source_pdf_page     SMALLINT UNSIGNED NOT NULL,
    geography_level     VARCHAR(24) NOT NULL,
    geography_name      VARCHAR(120) NOT NULL,
    indicator           VARCHAR(160) NOT NULL,
    subgroup            VARCHAR(100) NOT NULL DEFAULT '',
    unit                VARCHAR(40) NOT NULL,
    numeric_value       DECIMAL(14,4) DEFAULT NULL,
    raw_value           VARCHAR(40) NOT NULL DEFAULT '',
    value_status        ENUM('reported', 'dash', 'blank', 'not_available') NOT NULL,
    imported_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                                      ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_survey_source_record (data_source_id, record_key),
    INDEX idx_survey_table_geo (table_number, geography_level, geography_name),
    INDEX idx_survey_indicator (indicator, subgroup),
    CONSTRAINT chk_survey_observation_season CHECK (season IN ('A', 'B', 'C')),
    CONSTRAINT chk_survey_observation_table CHECK (table_number BETWEEN 1 AND 99),
    CONSTRAINT chk_survey_observation_value CHECK (
        (value_status = 'reported' AND numeric_value IS NOT NULL)
        OR (value_status IN ('dash', 'blank', 'not_available') AND numeric_value IS NULL)
    ),
    FOREIGN KEY (data_source_id)
        REFERENCES agricultural_data_sources(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- agro_suitability: per-district scores with auditable provenance
-- ------------------------------------------------------------
CREATE TABLE agro_suitability (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    district_id         INT UNSIGNED NOT NULL,
    crop_id             INT UNSIGNED NOT NULL,
    soil_score          DECIMAL(5,2) NOT NULL,
    climate_score       DECIMAL(5,2) NOT NULL,
    erosion_control_score DECIMAL(5,2) DEFAULT NULL,
    nutrient_balance_score DECIMAL(5,2) DEFAULT NULL,
    soil_structure_score DECIMAL(5,2) DEFAULT NULL,
    erosion_risk        ENUM('low','medium','high') NOT NULL,
    fertilizer_note     VARCHAR(255) DEFAULT NULL,
    source              VARCHAR(60) NOT NULL,
    source_reference    VARCHAR(255) DEFAULT NULL,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_district_crop (district_id, crop_id),
    INDEX idx_crop (crop_id),
    FOREIGN KEY (district_id) REFERENCES districts(id) ON DELETE CASCADE,
    FOREIGN KEY (crop_id) REFERENCES crops(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Convenience view: latest known price per food
-- ------------------------------------------------------------
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
