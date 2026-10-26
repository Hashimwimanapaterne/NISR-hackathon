-- Apply once after the agricultural_data_sources table exists.
-- Season B report observations are reference evidence and are not scoring inputs.

CREATE TABLE IF NOT EXISTS agricultural_survey_observations (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    data_source_id      INT UNSIGNED NOT NULL,
    record_key          CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    survey_year         SMALLINT UNSIGNED NOT NULL,
    season              CHAR(1) NOT NULL,
    table_number        SMALLINT UNSIGNED NOT NULL,
    table_title         VARCHAR(255) NOT NULL,
    source_pdf_page     SMALLINT UNSIGNED NOT NULL,
    geography_level     VARCHAR(24) NOT NULL,
    geography_name      VARCHAR(120) NOT NULL,
    indicator           VARCHAR(160) NOT NULL,
    subgroup            VARCHAR(100) NOT NULL DEFAULT '',
    unit                VARCHAR(40) NOT NULL,
    numeric_value       DECIMAL(14,4) DEFAULT NULL,
    raw_value           VARCHAR(40) NOT NULL DEFAULT '',
    value_status        ENUM('reported', 'dash', 'blank', 'not_available') NOT NULL,
    imported_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                                      ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_survey_source_record (data_source_id, record_key),
    INDEX idx_survey_table_geo (table_number, geography_level, geography_name),
    INDEX idx_survey_indicator (indicator, subgroup),
    CONSTRAINT chk_survey_observation_season CHECK (season IN ('A', 'B', 'C')),
    CONSTRAINT chk_survey_observation_table CHECK (table_number BETWEEN 1 AND 99),
    CONSTRAINT chk_survey_observation_value CHECK (
        (value_status = 'reported' AND numeric_value IS NOT NULL)
        OR (value_status IN ('dash', 'blank', 'not_available') AND numeric_value IS NULL)
    ),
    FOREIGN KEY (data_source_id)
        REFERENCES agricultural_data_sources(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
