-- Apply once to an existing database after all required schema migrations.
-- Back up the database before applying. This removes only rows written by the
-- known demo seed and leaves separately sourced/admin-entered data untouched.

ALTER TABLE crops
    MODIFY typical_yield_kg_per_hectare DECIMAL(8,2) DEFAULT NULL;

DELETE FROM prices
WHERE source = 'sample_placeholder';

DELETE FROM agro_suitability
WHERE source = 'illustrative_estimate';

DELETE FROM nutrient_profiles
WHERE source_note LIKE 'Illustrative seed estimate%';

UPDATE crops
SET typical_yield_kg_per_hectare = NULL,
    harvest_days_min = NULL,
    harvest_days_max = NULL,
    harvest_duration_note = NULL,
    notes = NULL,
    rotation_guidance = NULL,
    soil_stewardship_guidance = NULL
WHERE notes LIKE 'Illustrative planning yield%'
   OR harvest_duration_note LIKE 'Indicative general maturity range%';
