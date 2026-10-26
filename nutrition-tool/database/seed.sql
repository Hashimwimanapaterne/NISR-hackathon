-- Lookup catalogs only. No prices, nutrient values, yields, or suitability
-- scores are seeded; add those only from documented, verifiable sources.

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

INSERT INTO crops (name, food_id, typical_yield_kg_per_hectare)
SELECT v.crop_name, f.id, NULL
FROM (
    SELECT 'Potato' AS crop_name, 'Irish potatoes' AS food_name
    UNION ALL SELECT 'Rice', 'White rice'
    UNION ALL SELECT 'Maize', 'Maize flour'
    UNION ALL SELECT 'Cassava', 'Cassava (fresh)'
    UNION ALL SELECT 'Sorghum', 'Sorghum flour'
    UNION ALL SELECT 'Common beans', 'Dry beans (mixed)'
    UNION ALL SELECT 'Soybean', 'Soybeans'
    UNION ALL SELECT 'Sweet potato', 'Sweet potatoes'
    UNION ALL SELECT 'Avocado', 'Avocado'
    UNION ALL SELECT 'Cabbage', 'Cabbage'
    UNION ALL SELECT 'Carrot', 'Carrots'
    UNION ALL SELECT 'Amaranth (dodo)', 'Dodo (amaranth greens)'
    UNION ALL SELECT 'Plantain (matoke)', 'Green bananas (matoke)'
    UNION ALL SELECT 'Groundnut', 'Groundnuts (shelled)'
    UNION ALL SELECT 'Onion', 'Onions'
    UNION ALL SELECT 'Banana', 'Ripe bananas'
    UNION ALL SELECT 'Tomato', 'Tomatoes'
) AS v
INNER JOIN foods f ON f.name = v.food_name;
