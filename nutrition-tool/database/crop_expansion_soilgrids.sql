-- One-time migration for an existing Crop Advisor database.
-- Adds source provenance and six additional, district-wide crop entries.
-- New suitability scores are deliberately neutral placeholders, not measured
-- data. Replace them with RwaSIS or locally interpreted SoilGrids data.

ALTER TABLE agro_suitability
    ADD COLUMN source_reference VARCHAR(255) DEFAULT NULL AFTER source;

INSERT INTO crops (name, food_id, typical_yield_kg_per_hectare, notes)
SELECT candidate.crop_name, f.id, candidate.yield_kg_per_hectare, candidate.notes
FROM (
    SELECT 'Maize' AS crop_name, 'Maize flour' AS food_name, 2000 AS yield_kg_per_hectare,
           'Illustrative planning yield, verify with RAB/local data.' AS notes
    UNION ALL SELECT 'Cassava', 'Cassava (fresh)', 12000, 'Illustrative planning yield, verify with RAB/local data.'
    UNION ALL SELECT 'Sorghum', 'Sorghum flour', 1500, 'Illustrative planning yield, verify with RAB/local data.'
    UNION ALL SELECT 'Common beans', 'Dry beans (mixed)', 1000, 'Illustrative planning yield, verify with RAB/local data.'
    UNION ALL SELECT 'Soybean', 'Soybeans', 1500, 'Illustrative planning yield, verify with RAB/local data.'
    UNION ALL SELECT 'Sweet potato', 'Sweet potatoes', 8000, 'Illustrative planning yield, verify with RAB/local data.'
) AS candidate
INNER JOIN foods f ON f.name = candidate.food_name
LEFT JOIN crops existing ON existing.name = candidate.crop_name
WHERE existing.id IS NULL;

INSERT INTO agro_suitability (
    district_id, crop_id, soil_score, climate_score, erosion_risk,
    fertilizer_note, source, source_reference
)
SELECT
    d.id,
    c.id,
    50,
    50,
    'medium',
    'Neutral placeholder scores only, replace with district-specific agronomic data.',
    'illustrative_estimate',
    NULL
FROM districts d
CROSS JOIN crops c
LEFT JOIN agro_suitability a ON a.district_id = d.id AND a.crop_id = c.id
WHERE c.name IN ('Maize', 'Cassava', 'Sorghum', 'Common beans', 'Soybean', 'Sweet potato')
  AND a.id IS NULL;
