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

INSERT INTO districts (name, province) VALUES
('Nyarugenge', 'Kigali City'), ('Gasabo', 'Kigali City'), ('Kicukiro', 'Kigali City'),
('Nyanza', 'Southern'), ('Gisagara', 'Southern'), ('Nyaruguru', 'Southern'),
('Huye', 'Southern'), ('Nyamagabe', 'Southern'), ('Ruhango', 'Southern'),
('Muhanga', 'Southern'), ('Kamonyi', 'Southern'),
('Karongi', 'Western'), ('Rutsiro', 'Western'), ('Rubavu', 'Western'),
('Nyabihu', 'Western'), ('Ngororero', 'Western'), ('Rusizi', 'Western'),
('Nyamasheke', 'Western'),
('Rulindo', 'Northern'), ('Gakenke', 'Northern'), ('Musanze', 'Northern'),
('Burera', 'Northern'), ('Gicumbi', 'Northern'),
('Rwamagana', 'Eastern'), ('Nyagatare', 'Eastern'), ('Gatsibo', 'Eastern'),
('Kayonza', 'Eastern'), ('Kirehe', 'Eastern'), ('Ngoma', 'Eastern'),
('Bugesera', 'Eastern');

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

-- Crop yields and initial suitability are deliberately illustrative.
-- Replace yield estimates and neutral suitability scores with validated
-- RAB/local data or documented SoilGrids-derived assessments.
INSERT INTO crops (name, food_id, typical_yield_kg_per_hectare, notes)
SELECT v.crop_name, f.id, v.yield_kg_per_hectare, v.notes
FROM (
    SELECT 'Potato' AS crop_name, 'Irish potatoes' AS food_name, 8000 AS yield_kg_per_hectare,
           'Illustrative planning yield, verify with RAB/local data.' AS notes
    UNION ALL SELECT 'Rice', 'White rice', 5000, 'Illustrative planning yield, verify with RAB/local data.'
    UNION ALL SELECT 'Maize', 'Maize flour', 2000, 'Illustrative planning yield, verify with RAB/local data.'
    UNION ALL SELECT 'Cassava', 'Cassava (fresh)', 12000, 'Illustrative planning yield, verify with RAB/local data.'
    UNION ALL SELECT 'Sorghum', 'Sorghum flour', 1500, 'Illustrative planning yield, verify with RAB/local data.'
    UNION ALL SELECT 'Common beans', 'Dry beans (mixed)', 1000, 'Illustrative planning yield, verify with RAB/local data.'
    UNION ALL SELECT 'Soybean', 'Soybeans', 1500, 'Illustrative planning yield, verify with RAB/local data.'
    UNION ALL SELECT 'Sweet potato', 'Sweet potatoes', 8000, 'Illustrative planning yield, verify with RAB/local data.'
    UNION ALL SELECT 'Avocado', 'Avocado', 8000, 'Illustrative planning yield, verify with RAB/local data.'
    UNION ALL SELECT 'Cabbage', 'Cabbage', 25000, 'Illustrative planning yield, verify with RAB/local data.'
    UNION ALL SELECT 'Carrot', 'Carrots', 18000, 'Illustrative planning yield, verify with RAB/local data.'
    UNION ALL SELECT 'Amaranth (dodo)', 'Dodo (amaranth greens)', 10000, 'Illustrative planning yield, verify with RAB/local data.'
    UNION ALL SELECT 'Plantain (matoke)', 'Green bananas (matoke)', 12000, 'Illustrative planning yield, verify with RAB/local data.'
    UNION ALL SELECT 'Groundnut', 'Groundnuts (shelled)', 1500, 'Illustrative planning yield, verify with RAB/local data.'
    UNION ALL SELECT 'Onion', 'Onions', 15000, 'Illustrative planning yield, verify with RAB/local data.'
    UNION ALL SELECT 'Banana', 'Ripe bananas', 12000, 'Illustrative planning yield, verify with RAB/local data.'
    UNION ALL SELECT 'Tomato', 'Tomatoes', 20000, 'Illustrative planning yield, verify with RAB/local data.'
) AS v
INNER JOIN foods f ON f.name = v.food_name;

UPDATE crops
SET
    rotation_guidance = CASE name
        WHEN 'Potato' THEN 'Potato → beans/soybean/groundnut → maize or sorghum → potato. Avoid consecutive potato, tomato, or other nightshades.'
        WHEN 'Rice' THEN 'Rice → legume during a suitable drained phase → cereal or cover crop → rice. Avoid continuous rice monoculture.'
        WHEN 'Maize' THEN 'Maize → beans, soybean, or groundnut → root crop or cover crop → maize. Avoid repeated cereal cropping.'
        WHEN 'Cassava' THEN 'Cassava → legume → cereal or cover crop → cassava. Keep soil covered during establishment and after harvest.'
        WHEN 'Sorghum' THEN 'Sorghum → legume → root crop or cover crop → sorghum. Retain safe residues and avoid repeated cereals.'
        WHEN 'Common beans' THEN 'Beans → maize or sorghum → root crop or cover crop → beans. Avoid consecutive legumes where disease pressure is high.'
        WHEN 'Soybean' THEN 'Soybean → maize or sorghum → root crop or cover crop → soybean. Avoid consecutive legumes where disease pressure is high.'
        WHEN 'Sweet potato' THEN 'Sweet potato → cereal or legume → leafy cover crop → sweet potato. Avoid consecutive root crops.'
        WHEN 'Avocado' THEN 'In young orchards, use locally suitable legume or grass cover between rows; maintain a living understory rather than rotating established trees.'
        WHEN 'Cabbage' THEN 'Cabbage → legume → cereal or root crop → cabbage. Avoid consecutive cabbage or other brassicas.'
        WHEN 'Carrot' THEN 'Carrot → cereal or legume → leafy cover crop → carrot. Avoid consecutive carrot or related umbelliferous crops.'
        WHEN 'Amaranth (dodo)' THEN 'Amaranth → legume → cereal or root crop → amaranth. Avoid repeated leafy crops that remove nutrients.'
        WHEN 'Plantain (matoke)' THEN 'In established plantain, keep a suitable legume/grass understory and rotate cover species; do not disturb perennial mats for an annual rotation.'
        WHEN 'Groundnut' THEN 'Groundnut → maize or sorghum → root crop or cover crop → groundnut. Avoid continuous legumes and return safe residues.'
        WHEN 'Onion' THEN 'Onion → legume or cereal → root or leafy crop → onion. Avoid consecutive onion or other alliums.'
        WHEN 'Banana' THEN 'In established banana, maintain a suitable legume/grass understory and rotate cover species; do not disturb perennial mats for an annual rotation.'
        WHEN 'Tomato' THEN 'Tomato → legume or cereal → root crop or cover crop → tomato. Avoid consecutive tomato, potato, or other nightshades.'
    END,
    soil_stewardship_guidance = CASE name
        WHEN 'Potato' THEN 'Bare ridges can erode on slopes. Use contour-aligned ridges, mulch/cover crops, safe residue return, and soil-test-led fertility; avoid excessive tillage.'
        WHEN 'Rice' THEN 'Good bunds slow runoff, but damaged bunds and poor water control can cause erosion. Continuous flooding/puddling may harm structure; manage water and residues carefully.'
        WHEN 'Maize' THEN 'Rows may leave soil exposed and grain harvest removes nutrients. Retain safe residues, use cover crops and contour practices on slopes, and rotate with legumes.'
        WHEN 'Cassava' THEN 'Slow canopy establishment and long field occupancy can expose or exhaust soil. Intercrop/cover early, contour-plant on slopes, return residues, and rotate with legumes.'
        WHEN 'Sorghum' THEN 'A vigorous canopy and roots can protect soil, but removing all stalks reduces cover and organic matter. Retain safe residues and rotate with legumes.'
        WHEN 'Common beans' THEN 'Legumes can add nitrogen when well nodulated, but harvested grain and removed haulms export nutrients. Return safe residues and maintain soil cover.'
        WHEN 'Soybean' THEN 'Legumes can add nitrogen when well nodulated, but harvested grain and removed residues export nutrients. Return safe residues and rotate with cereals.'
        WHEN 'Sweet potato' THEN 'Spreading vines can cover soil after establishment; root harvest disturbs soil and removes nutrients. Maintain contour cover and rotate with cereals or legumes.'
        WHEN 'Avocado' THEN 'Perennial canopy and roots may reduce erosion if the orchard floor stays covered. Maintain understory and mulch; trees still remove nutrients and need balanced replenishment.'
        WHEN 'Cabbage' THEN 'Open beds can erode and leafy harvests remove nutrients. Mulch, use contour beds, replace nutrients based on soil tests, and rotate away from brassicas.'
        WHEN 'Carrot' THEN 'Fine seedbeds and root harvest can leave soil exposed and disturb structure. Mulch between rows, avoid over-tillage, and replenish exported nutrients.'
        WHEN 'Amaranth (dodo)' THEN 'Dense leafy growth can cover soil, but repeated leaf harvest can remove nutrients. Retain roots/residues where safe and rotate with legumes.'
        WHEN 'Plantain (matoke)' THEN 'Perennial canopy and mulch from leaves can protect soil; keep an understory to prevent bare ground and replenish nutrients removed in fruit.'
        WHEN 'Groundnut' THEN 'Legumes can add nitrogen when well nodulated, but pod lifting disturbs soil and residue removal exports nutrients. Harvest carefully and retain safe haulms.'
        WHEN 'Onion' THEN 'Sparse early canopy leaves soil exposed and bulbs remove nutrients. Mulch, use suitable intercrops/cover, limit tillage, and rotate away from alliums.'
        WHEN 'Banana' THEN 'Perennial canopy and returned leaves can reduce erosion and build organic matter. Keep a living understory; replenish nutrients exported in fruit.'
        WHEN 'Tomato' THEN 'Open rows and heavy fruit removal can expose and deplete soil. Mulch, stake to improve cover, use contour practices, and rotate away from nightshades.'
    END;

INSERT INTO agro_suitability (
    district_id, crop_id, soil_score, climate_score, erosion_risk,
    fertilizer_note, source, source_reference, erosion_control_score,
    nutrient_balance_score, soil_structure_score
)
SELECT
    d.id, c.id, 50, 50, 'medium',
    'Neutral placeholder scores only, replace with district-specific agronomic data.',
    'illustrative_estimate',
    NULL,
    CASE c.name
        WHEN 'Potato' THEN 30 WHEN 'Rice' THEN 65 WHEN 'Maize' THEN 35 WHEN 'Cassava' THEN 40
        WHEN 'Sorghum' THEN 60 WHEN 'Common beans' THEN 50 WHEN 'Soybean' THEN 45 WHEN 'Sweet potato' THEN 55
        WHEN 'Avocado' THEN 60 WHEN 'Cabbage' THEN 35 WHEN 'Carrot' THEN 40 WHEN 'Amaranth (dodo)' THEN 60
        WHEN 'Plantain (matoke)' THEN 70 WHEN 'Groundnut' THEN 50 WHEN 'Onion' THEN 25
        WHEN 'Banana' THEN 65 WHEN 'Tomato' THEN 30 ELSE 50
    END,
    CASE c.name
        WHEN 'Potato' THEN 35 WHEN 'Rice' THEN 40 WHEN 'Maize' THEN 35 WHEN 'Cassava' THEN 35
        WHEN 'Sorghum' THEN 40 WHEN 'Common beans' THEN 75 WHEN 'Soybean' THEN 75 WHEN 'Sweet potato' THEN 40
        WHEN 'Avocado' THEN 50 WHEN 'Cabbage' THEN 35 WHEN 'Carrot' THEN 35 WHEN 'Amaranth (dodo)' THEN 55
        WHEN 'Plantain (matoke)' THEN 50 WHEN 'Groundnut' THEN 75 WHEN 'Onion' THEN 30
        WHEN 'Banana' THEN 50 WHEN 'Tomato' THEN 30 ELSE 50
    END,
    CASE c.name
        WHEN 'Potato' THEN 40 WHEN 'Rice' THEN 35 WHEN 'Maize' THEN 40 WHEN 'Cassava' THEN 45
        WHEN 'Sorghum' THEN 50 WHEN 'Common beans' THEN 55 WHEN 'Soybean' THEN 50 WHEN 'Sweet potato' THEN 55
        WHEN 'Avocado' THEN 70 WHEN 'Cabbage' THEN 40 WHEN 'Carrot' THEN 40 WHEN 'Amaranth (dodo)' THEN 55
        WHEN 'Plantain (matoke)' THEN 75 WHEN 'Groundnut' THEN 55 WHEN 'Onion' THEN 35
        WHEN 'Banana' THEN 70 WHEN 'Tomato' THEN 35 ELSE 50
    END
FROM districts d
CROSS JOIN crops c;

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
