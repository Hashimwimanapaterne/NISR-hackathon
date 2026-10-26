-- One-time migration for an existing Crop Advisor database.
-- Adds all remaining plant-food crops already represented in the food catalog.

ALTER TABLE crops
    MODIFY typical_yield_kg_per_hectare DECIMAL(8,2) DEFAULT NULL;

INSERT INTO crops (name, food_id, typical_yield_kg_per_hectare, notes)
SELECT candidate.crop_name, f.id, NULL, NULL
FROM (
    SELECT 'Avocado' AS crop_name, 'Avocado' AS food_name
    UNION ALL SELECT 'Cabbage', 'Cabbage'
    UNION ALL SELECT 'Carrot', 'Carrots'
    UNION ALL SELECT 'Amaranth (dodo)', 'Dodo (amaranth greens)'
    UNION ALL SELECT 'Plantain (matoke)', 'Green bananas (matoke)'
    UNION ALL SELECT 'Groundnut', 'Groundnuts (shelled)'
    UNION ALL SELECT 'Onion', 'Onions'
    UNION ALL SELECT 'Banana', 'Ripe bananas'
    UNION ALL SELECT 'Tomato', 'Tomatoes'
) AS candidate
INNER JOIN foods f ON f.name = candidate.food_name
LEFT JOIN crops existing ON existing.name = candidate.crop_name
WHERE existing.id IS NULL;
