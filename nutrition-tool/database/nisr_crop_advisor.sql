-- Apply once to an existing database after crop_statistics_multisource.sql.
-- Back up the database before applying.

ALTER TABLE crops
    ADD COLUMN harvest_days_min SMALLINT UNSIGNED DEFAULT NULL
        AFTER typical_yield_kg_per_hectare,
    ADD COLUMN harvest_days_max SMALLINT UNSIGNED DEFAULT NULL
        AFTER harvest_days_min,
    ADD COLUMN harvest_duration_note VARCHAR(255) DEFAULT NULL
        AFTER harvest_days_max,
    ADD CONSTRAINT chk_crop_harvest_days CHECK (
        (harvest_days_min IS NULL AND harvest_days_max IS NULL)
        OR (harvest_days_min IS NOT NULL AND harvest_days_max IS NOT NULL
            AND harvest_days_min > 0 AND harvest_days_max >= harvest_days_min)
    );

ALTER TABLE district_crop_statistics
    MODIFY cultivated_area_ha DECIMAL(12,3) DEFAULT NULL,
    MODIFY production_tonnes DECIMAL(14,3) DEFAULT NULL;
