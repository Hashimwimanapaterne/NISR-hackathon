-- One-time migration for an existing Crop Advisor database.
-- Adds all remaining plant-food crops already represented in the food catalog.
-- Yield values and district suitability scores are illustrative placeholders,
-- not locally validated agronomic recommendations.

INSERT INTO crops (name, food_id, typical_yield_kg_per_hectare, notes)
SELECT candidate.crop_name, f.id, candidate.yield_kg_per_hectare, candidate.notes
FROM (
    SELECT 'Avocado' AS crop_name, 'Avocado' AS food_name, 8000 AS yield_kg_per_hectare,
           'Illustrative planning yield, verify with RAB/local data.' AS notes
    UNION ALL SELECT 'Cabbage', 'Cabbage', 25000, 'Illustrative planning yield, verify with RAB/local data.'
    UNION ALL SELECT 'Carrot', 'Carrots', 18000, 'Illustrative planning yield, verify with RAB/local data.'
    UNION ALL SELECT 'Amaranth (dodo)', 'Dodo (amaranth greens)', 10000, 'Illustrative planning yield, verify with RAB/local data.'
    UNION ALL SELECT 'Plantain (matoke)', 'Green bananas (matoke)', 12000, 'Illustrative planning yield, verify with RAB/local data.'
    UNION ALL SELECT 'Groundnut', 'Groundnuts (shelled)', 1500, 'Illustrative planning yield, verify with RAB/local data.'
    UNION ALL SELECT 'Onion', 'Onions', 15000, 'Illustrative planning yield, verify with RAB/local data.'
    UNION ALL SELECT 'Banana', 'Ripe bananas', 12000, 'Illustrative planning yield, verify with RAB/local data.'
    UNION ALL SELECT 'Tomato', 'Tomatoes', 20000, 'Illustrative planning yield, verify with RAB/local data.'
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
WHERE c.name IN (
    'Avocado', 'Cabbage', 'Carrot', 'Amaranth (dodo)', 'Plantain (matoke)',
    'Groundnut', 'Onion', 'Banana', 'Tomato'
)
  AND a.id IS NULL;
