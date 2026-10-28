-- Apply once to an existing Crop Advisor database after backing it up.
-- Adds optional fields for locally documented guidance and measured scores.

ALTER TABLE crops
    ADD COLUMN rotation_guidance VARCHAR(500) DEFAULT NULL,
    ADD COLUMN soil_stewardship_guidance VARCHAR(600) DEFAULT NULL;

ALTER TABLE agro_suitability
    ADD COLUMN erosion_control_score DECIMAL(5,2) DEFAULT NULL,
    ADD COLUMN nutrient_balance_score DECIMAL(5,2) DEFAULT NULL,
    ADD COLUMN soil_structure_score DECIMAL(5,2) DEFAULT NULL;
