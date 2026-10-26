-- One-time migration for an existing Crop Advisor database.
-- Adds source provenance and six additional, district-wide crop entries.

ALTER TABLE agro_suitability
    ADD COLUMN source_reference VARCHAR(255) DEFAULT NULL AFTER source;

ALTER TABLE crops
    MODIFY typical_yield_kg_per_hectare DECIMAL(8,2) DEFAULT NULL;

INSERT INTO crops (name, food_id, typical_yield_kg_per_hectare, notes)
SELECT candidate.crop_name, f.id, NULL, NULL
FROM (
    SELECT 'Maize' AS crop_name, 'Maize flour' AS food_name
    UNION ALL SELECT 'Cassava', 'Cassava (fresh)'
    UNION ALL SELECT 'Sorghum', 'Sorghum flour'
    UNION ALL SELECT 'Common beans', 'Dry beans (mixed)'
    UNION ALL SELECT 'Soybean', 'Soybeans'
    UNION ALL SELECT 'Sweet potato', 'Sweet potatoes'
) AS candidate
INNER JOIN foods f ON f.name = candidate.food_name
LEFT JOIN crops existing ON existing.name = candidate.crop_name
WHERE existing.id IS NULL;
