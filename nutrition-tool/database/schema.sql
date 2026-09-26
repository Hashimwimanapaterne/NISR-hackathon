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
-- nutrient_profiles: nutrition content per 100g of edible portion
-- Source: FAO/INFOODS regional food composition tables (approximate
-- reference values — replace with validated local data before
-- final submission; see README "Data sources" section).
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
    source          VARCHAR(60)  NOT NULL DEFAULT 'manual',  -- 'manual' | 'esoko_import'
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
-- Convenience view: latest known price per food
-- ------------------------------------------------------------
CREATE OR REPLACE VIEW latest_prices AS
SELECT p.food_id, p.price_rwf, p.market_name, p.district, p.recorded_on
FROM prices p
INNER JOIN (
    SELECT food_id, MAX(recorded_on) AS max_date
    FROM prices
    GROUP BY food_id
) latest ON p.food_id = latest.food_id AND p.recorded_on = latest.max_date;
