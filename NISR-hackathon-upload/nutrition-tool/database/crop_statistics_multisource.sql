-- Apply once after crop_expansion_food_provenance.sql to an existing database.
-- Back up the database before applying. This preserves all existing observations and sources.

ALTER TABLE agricultural_data_sources
    ADD COLUMN is_active_for_scoring TINYINT(1) NOT NULL DEFAULT 1
        AFTER reuse_terms;

ALTER TABLE district_crop_statistics
    ADD COLUMN harvested_area_ha DECIMAL(12,3) DEFAULT NULL
        AFTER production_tonnes,
    ADD COLUMN published_yield_kg_per_hectare DECIMAL(12,3) DEFAULT NULL
        AFTER harvested_area_ha,
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (district_id, crop_id, season, survey_year, data_source_id),
    ADD CONSTRAINT chk_crop_stats_harvested_area
        CHECK (harvested_area_ha IS NULL OR harvested_area_ha > 0),
    ADD CONSTRAINT chk_crop_stats_published_yield
        CHECK (published_yield_kg_per_hectare IS NULL OR published_yield_kg_per_hectare >= 0);
