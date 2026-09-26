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

-- Nutrient profiles (per 100g of the food AS PURCHASED — raw/dry for
-- staples, as-is for milk/oil/sugar/eggs). Values are approximate
-- reference figures typical of these food groups, not lab results —
-- validate against a Rwandan/regional food composition table before
-- final submission. Columns: calories, protein, fat, carbs, fiber,
-- iron, zinc, calcium, potassium, vitamin A, vitamin C, folate, B12.
-- Note: White rice was corrected from an earlier draft that had
-- accidentally used cooked-rice calories against a raw-rice price.
INSERT INTO nutrient_profiles (
    food_id, calories_kcal, protein_g, fat_g, carbohydrates_g, fiber_g,
    iron_mg, zinc_mg, calcium_mg, potassium_mg, vitamin_a_ug, vitamin_c_mg,
    folate_ug, vitamin_b12_ug, source_note
)
SELECT id, v.calories, v.protein, v.fat, v.carbs, v.fiber,
       v.iron, v.zinc, v.calcium, v.potassium, v.vit_a, v.vit_c, v.folate, v.b12,
       'Approximate reference value — verify before final submission'
FROM foods f
JOIN (
    SELECT 'White rice' AS name, 365 AS calories, 7.1 AS protein, 0.7 AS fat, 80.0 AS carbs, 1.3 AS fiber,
           0.8 AS iron, 1.1 AS zinc, 10 AS calcium, 115 AS potassium, 0 AS vit_a, 0 AS vit_c, 8 AS folate, 0 AS b12
    UNION ALL SELECT 'Maize flour', 361, 9.4, 4.7, 74.0, 7.3, 2.7, 2.2, 7, 287, 11, 0, 25, 0
    UNION ALL SELECT 'Sorghum flour', 339, 11.3, 3.3, 75.0, 6.3, 3.4, 1.7, 13, 350, 0, 0, 20, 0
    UNION ALL SELECT 'Irish potatoes', 77, 2.0, 0.1, 17.0, 2.2, 0.8, 0.3, 12, 425, 0, 20.0, 16, 0
    UNION ALL SELECT 'Sweet potatoes', 86, 1.6, 0.1, 20.0, 3.0, 0.6, 0.3, 30, 337, 709, 2.4, 11, 0
    UNION ALL SELECT 'Cassava (fresh)', 160, 1.4, 0.3, 38.0, 1.8, 0.3, 0.3, 16, 271, 1, 20.6, 27, 0
    UNION ALL SELECT 'Cassava flour', 340, 1.5, 0.5, 84.0, 3.5, 1.6, 0.3, 40, 150, 0, 0, 0, 0
    UNION ALL SELECT 'Dry beans (mixed)', 333, 21.0, 1.2, 60.0, 15.5, 6.7, 2.8, 113, 1400, 0, 4.5, 394, 0
    UNION ALL SELECT 'Groundnuts (shelled)', 567, 25.8, 49.2, 16.0, 8.5, 4.6, 3.3, 92, 705, 0, 0, 240, 0
    UNION ALL SELECT 'Soybeans', 446, 36.5, 19.9, 30.0, 9.3, 15.7, 4.9, 277, 1797, 1, 6.0, 375, 0
    UNION ALL SELECT 'Green bananas (matoke)', 122, 1.3, 0.4, 31.9, 2.3, 0.6, 0.2, 6, 499, 38, 18.4, 22, 0
    UNION ALL SELECT 'Ripe bananas', 89, 1.1, 0.3, 22.8, 2.6, 0.3, 0.2, 5, 358, 3, 8.7, 20, 0
    UNION ALL SELECT 'Avocado', 160, 2.0, 14.7, 8.5, 6.7, 0.6, 0.6, 12, 485, 7, 10.0, 81, 0
    UNION ALL SELECT 'Tomatoes', 18, 0.9, 0.2, 3.9, 1.2, 0.3, 0.2, 10, 237, 42, 14.0, 15, 0
    UNION ALL SELECT 'Onions', 40, 1.1, 0.1, 9.3, 1.7, 0.2, 0.2, 23, 146, 0, 7.4, 19, 0
    UNION ALL SELECT 'Cabbage', 25, 1.3, 0.1, 5.8, 2.5, 0.5, 0.2, 40, 170, 5, 36.6, 43, 0
    UNION ALL SELECT 'Carrots', 41, 0.9, 0.2, 9.6, 2.8, 0.3, 0.2, 33, 320, 835, 5.9, 19, 0
    UNION ALL SELECT 'Dodo (amaranth greens)', 23, 2.5, 0.3, 4.0, 2.1, 2.3, 0.9, 215, 611, 146, 43.3, 85, 0
    UNION ALL SELECT 'Eggs', 155, 13.0, 11.0, 1.1, 0, 1.8, 1.3, 56, 138, 160, 0, 47, 0.9
    UNION ALL SELECT 'Milk (fresh)', 61, 3.2, 3.3, 4.8, 0, 0.03, 0.4, 113, 143, 46, 0, 5, 0.4
    UNION ALL SELECT 'Dry fish (indagara)', 305, 55.0, 8.0, 0, 0, 3.0, 3.5, 900, 700, 15, 0, 0, 5.0
    UNION ALL SELECT 'Beef', 250, 26.0, 17.0, 0, 0, 2.6, 4.8, 18, 318, 0, 0, 6, 2.6
    UNION ALL SELECT 'Chicken', 239, 27.3, 13.6, 0, 0, 1.3, 1.5, 15, 223, 20, 0, 6, 0.3
    UNION ALL SELECT 'Cooking oil', 884, 0, 100.0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0
    UNION ALL SELECT 'Sugar', 387, 0, 0, 100.0, 0, 0, 0, 1, 2, 0, 0, 0, 0
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
