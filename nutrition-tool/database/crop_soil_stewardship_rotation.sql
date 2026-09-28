-- One-time migration for an existing Crop Advisor database.
-- Crop scores and prose are illustrative management tendencies, not measured outcomes.

ALTER TABLE crops
    ADD COLUMN rotation_guidance VARCHAR(500) DEFAULT NULL,
    ADD COLUMN soil_stewardship_guidance VARCHAR(600) DEFAULT NULL;

ALTER TABLE agro_suitability
    ADD COLUMN erosion_control_score DECIMAL(5,2) NOT NULL DEFAULT 50.00,
    ADD COLUMN nutrient_balance_score DECIMAL(5,2) NOT NULL DEFAULT 50.00,
    ADD COLUMN soil_structure_score DECIMAL(5,2) NOT NULL DEFAULT 50.00;

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
        ELSE 'Alternate crop families and include a locally suitable legume or cover crop where agronomically appropriate.'
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
        ELSE 'Use locally appropriate ground cover and residue management, monitor nutrient removal, and rotate crop families where feasible.'
    END;

UPDATE agro_suitability a
INNER JOIN crops c ON c.id = a.crop_id
SET
    a.erosion_control_score = CASE c.name
        WHEN 'Potato' THEN 30 WHEN 'Rice' THEN 65 WHEN 'Maize' THEN 35 WHEN 'Cassava' THEN 40
        WHEN 'Sorghum' THEN 60 WHEN 'Common beans' THEN 50 WHEN 'Soybean' THEN 45 WHEN 'Sweet potato' THEN 55
        WHEN 'Avocado' THEN 60 WHEN 'Cabbage' THEN 35 WHEN 'Carrot' THEN 40 WHEN 'Amaranth (dodo)' THEN 60
        WHEN 'Plantain (matoke)' THEN 70 WHEN 'Groundnut' THEN 50 WHEN 'Onion' THEN 25
        WHEN 'Banana' THEN 65 WHEN 'Tomato' THEN 30 ELSE 50
    END,
    a.nutrient_balance_score = CASE c.name
        WHEN 'Potato' THEN 35 WHEN 'Rice' THEN 40 WHEN 'Maize' THEN 35 WHEN 'Cassava' THEN 35
        WHEN 'Sorghum' THEN 40 WHEN 'Common beans' THEN 75 WHEN 'Soybean' THEN 75 WHEN 'Sweet potato' THEN 40
        WHEN 'Avocado' THEN 50 WHEN 'Cabbage' THEN 35 WHEN 'Carrot' THEN 35 WHEN 'Amaranth (dodo)' THEN 55
        WHEN 'Plantain (matoke)' THEN 50 WHEN 'Groundnut' THEN 75 WHEN 'Onion' THEN 30
        WHEN 'Banana' THEN 50 WHEN 'Tomato' THEN 30 ELSE 50
    END,
    a.soil_structure_score = CASE c.name
        WHEN 'Potato' THEN 40 WHEN 'Rice' THEN 35 WHEN 'Maize' THEN 40 WHEN 'Cassava' THEN 45
        WHEN 'Sorghum' THEN 50 WHEN 'Common beans' THEN 55 WHEN 'Soybean' THEN 50 WHEN 'Sweet potato' THEN 55
        WHEN 'Avocado' THEN 70 WHEN 'Cabbage' THEN 40 WHEN 'Carrot' THEN 40 WHEN 'Amaranth (dodo)' THEN 55
        WHEN 'Plantain (matoke)' THEN 75 WHEN 'Groundnut' THEN 55 WHEN 'Onion' THEN 35
        WHEN 'Banana' THEN 70 WHEN 'Tomato' THEN 35 ELSE 50
    END;
