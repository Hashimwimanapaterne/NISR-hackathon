-- ============================================================
-- Sample / demo seed data
--
-- IMPORTANT: The prices below are PLACEHOLDER values for demo
-- purposes only — they are NOT live e-Soko data. Before the
-- hackathon submission, replace the `prices` rows with a real
-- e-Soko export/import (see /admin panel or a future import
-- script). Nutrient values are approximate figures typical of
-- these food groups from FAO/INFOODS-style regional food
-- composition references, per 100g of edible portion — treat
-- them as reasonable defaults, not validated lab data.
-- ============================================================

USE umurima_nutrition;

INSERT INTO foods (name, category, unit_label, grams_per_unit) VALUES
('White rice',              'Grains',          'kg',       1000),
('Maize flour',             'Grains',          'kg',       1000),
('Sorghum flour',           'Grains',          'kg',       1000),
('Irish potatoes',          'Tubers',          'kg',       1000),
('Sweet potatoes',          'Tubers',          'kg',       1000),
('Cassava (fresh)',         'Tubers',          'kg',       1000),
('Cassava flour',           'Tubers',          'kg',       1000),
('Dry beans (mixed)',       'Legumes',         'kg',       1000),
('Groundnuts (shelled)',    'Legumes',         'kg',       1000),
('Soybeans',                'Legumes',         'kg',       1000),
('Green bananas (matoke)',  'Fruits & Starch', 'kg',       1000),
('Ripe bananas',            'Fruits & Starch', 'kg',       1000),
('Avocado',                 'Fruits & Starch', 'piece',    200),
('Tomatoes',                'Vegetables',      'kg',       1000),
('Onions',                  'Vegetables',      'kg',       1000),
('Cabbage',                 'Vegetables',      'kg',       1000),
('Carrots',                 'Vegetables',      'kg',       1000),
('Dodo (amaranth greens)',  'Vegetables',      'kg',       1000),
('Eggs',                    'Animal Protein',  'tray(30)', 1650),
('Milk (fresh)',            'Animal Protein',  'litre',    1030),
('Dry fish (indagara)',     'Animal Protein',  'kg',       1000),
('Beef',                    'Animal Protein',  'kg',       1000),
('Chicken',                 'Animal Protein',  'kg',       1000),
('Cooking oil',             'Fats & Oils',     'litre',    920),
('Sugar',                   'Other',           'kg',       1000);

-- Nutrient profiles (per 100g edible portion; eggs per 100g ≈ ~1.7 eggs)
INSERT INTO nutrient_profiles (food_id, calories_kcal, protein_g, iron_mg, calcium_mg, vitamin_a_ug, source_note)
SELECT id, v.calories, v.protein, v.iron, v.calcium, v.vit_a, 'Approximate reference value — verify before final submission'
FROM foods f
JOIN (
    SELECT 'White rice' AS name, 130 AS calories, 2.4 AS protein, 0.5 AS iron, 10 AS calcium, 0 AS vit_a
    UNION ALL SELECT 'Maize flour', 361, 9.4, 2.7, 7, 11
    UNION ALL SELECT 'Sorghum flour', 339, 11.3, 3.4, 13, 0
    UNION ALL SELECT 'Irish potatoes', 77, 2.0, 0.8, 12, 0
    UNION ALL SELECT 'Sweet potatoes', 86, 1.6, 0.6, 30, 709
    UNION ALL SELECT 'Cassava (fresh)', 160, 1.4, 0.3, 16, 1
    UNION ALL SELECT 'Cassava flour', 340, 1.5, 1.6, 40, 0
    UNION ALL SELECT 'Dry beans (mixed)', 333, 21.0, 6.7, 113, 0
    UNION ALL SELECT 'Groundnuts (shelled)', 567, 25.8, 4.6, 92, 0
    UNION ALL SELECT 'Soybeans', 446, 36.5, 15.7, 277, 1
    UNION ALL SELECT 'Green bananas (matoke)', 122, 1.3, 0.6, 6, 38
    UNION ALL SELECT 'Ripe bananas', 89, 1.1, 0.3, 5, 3
    UNION ALL SELECT 'Avocado', 160, 2.0, 0.6, 12, 7
    UNION ALL SELECT 'Tomatoes', 18, 0.9, 0.3, 10, 42
    UNION ALL SELECT 'Onions', 40, 1.1, 0.2, 23, 0
    UNION ALL SELECT 'Cabbage', 25, 1.3, 0.5, 40, 5
    UNION ALL SELECT 'Carrots', 41, 0.9, 0.3, 33, 835
    UNION ALL SELECT 'Dodo (amaranth greens)', 23, 2.5, 2.3, 215, 146
    UNION ALL SELECT 'Eggs', 155, 13.0, 1.8, 56, 160
    UNION ALL SELECT 'Milk (fresh)', 61, 3.2, 0.03, 113, 46
    UNION ALL SELECT 'Dry fish (indagara)', 305, 55.0, 3.0, 900, 15
    UNION ALL SELECT 'Beef', 250, 26.0, 2.6, 18, 0
    UNION ALL SELECT 'Chicken', 239, 27.3, 1.3, 15, 20
    UNION ALL SELECT 'Cooking oil', 884, 0, 0, 0, 0
    UNION ALL SELECT 'Sugar', 387, 0, 0, 1, 0
) v ON v.name = f.name;

-- Placeholder sample prices (RWF, per unit_label) — SAMPLE DATA ONLY
INSERT INTO prices (food_id, price_rwf, market_name, district, recorded_on, source)
SELECT id, v.price, 'Kimironko (sample)', 'Gasabo', CURDATE(), 'manual'
FROM foods f
JOIN (
    SELECT 'White rice' AS name, 1200 AS price
    UNION ALL SELECT 'Maize flour', 500
    UNION ALL SELECT 'Sorghum flour', 700
    UNION ALL SELECT 'Irish potatoes', 400
    UNION ALL SELECT 'Sweet potatoes', 350
    UNION ALL SELECT 'Cassava (fresh)', 300
    UNION ALL SELECT 'Cassava flour', 600
    UNION ALL SELECT 'Dry beans (mixed)', 1100
    UNION ALL SELECT 'Groundnuts (shelled)', 2500
    UNION ALL SELECT 'Soybeans', 1300
    UNION ALL SELECT 'Green bananas (matoke)', 350
    UNION ALL SELECT 'Ripe bananas', 500
    UNION ALL SELECT 'Avocado', 150
    UNION ALL SELECT 'Tomatoes', 700
    UNION ALL SELECT 'Onions', 800
    UNION ALL SELECT 'Cabbage', 300
    UNION ALL SELECT 'Carrots', 600
    UNION ALL SELECT 'Dodo (amaranth greens)', 400
    UNION ALL SELECT 'Eggs', 3800
    UNION ALL SELECT 'Milk (fresh)', 500
    UNION ALL SELECT 'Dry fish (indagara)', 4500
    UNION ALL SELECT 'Beef', 4000
    UNION ALL SELECT 'Chicken', 4500
    UNION ALL SELECT 'Cooking oil', 2200
    UNION ALL SELECT 'Sugar', 1400
) v ON v.name = f.name;
