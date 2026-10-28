<?php
/**
 * Import normalized NISR SAS Season B 2026 observations.
 *
 * Usage: php bin/import_nisr_sas_observations.php <observations.csv>
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';

const MAX_IMPORT_BYTES = 5 * 1024 * 1024;
const MAX_IMPORT_ROWS = 15000;

function fail_nisr_import(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

if ($argc !== 2) {
    fail_nisr_import('Usage: php bin/import_nisr_sas_observations.php <observations.csv>');
}

$csvPath = $argv[1];
if (!is_file($csvPath) || !is_readable($csvPath)) {
    fail_nisr_import('The observations CSV does not exist or cannot be read.');
}
$fileSize = filesize($csvPath);
if ($fileSize === false || $fileSize > MAX_IMPORT_BYTES) {
    fail_nisr_import('The observations CSV exceeds the 5 MB import limit.');
}

$headers = [
    'table_number',
    'table_title',
    'source_pdf_page',
    'geography_level',
    'geography_name',
    'indicator',
    'subgroup',
    'unit',
    'value',
    'raw_value',
    'value_status',
];
$handle = fopen($csvPath, 'rb');
if ($handle === false) {
    fail_nisr_import('Could not open the observations CSV.');
}
$csvHeaders = fgetcsv($handle, null, ',', '"', '');
if (!is_array($csvHeaders)) {
    fclose($handle);
    fail_nisr_import('CSV is empty or has an invalid header row.');
}
$csvHeaders = array_map(
    static fn ($header): string => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $header)),
    $csvHeaders
);
if ($csvHeaders !== $headers) {
    fclose($handle);
    fail_nisr_import('CSV headers do not match the normalized NISR observation format.');
}

$rows = [];
$seen = [];
$line = 1;
while (($record = fgetcsv($handle, null, ',', '"', '')) !== false) {
    $line++;
    if ($record === [null] || $record === []) {
        continue;
    }
    if (count($rows) >= MAX_IMPORT_ROWS) {
        fclose($handle);
        fail_nisr_import('CSV exceeds the 15,000-observation import limit.');
    }
    if (count($record) !== count($headers)) {
        fclose($handle);
        fail_nisr_import('CSV row ' . $line . ' has the wrong number of columns.');
    }

    $row = array_combine($headers, array_map('trim', $record));
    if (!is_array($row)) {
        fclose($handle);
        fail_nisr_import('CSV row ' . $line . ' could not be read.');
    }
    $tableNumber = filter_var($row['table_number'], FILTER_VALIDATE_INT);
    $pageNumber = filter_var($row['source_pdf_page'], FILTER_VALIDATE_INT);
    $geographyLevels = ['district', 'crop', 'fertilizer', 'national', 'farmer_type'];
    $units = [
        'Percentage',
        'Hectares',
        'Thousand hectares',
        'Kilograms per hectare',
        'Metric tonnes',
        'Metric tonnes per hectare',
    ];
    $validLabels = $row['table_title'] !== ''
        && strlen($row['table_title']) <= 255
        && $row['geography_name'] !== ''
        && strlen($row['geography_name']) <= 120
        && $row['indicator'] !== ''
        && strlen($row['indicator']) <= 160
        && strlen($row['subgroup']) <= 100
        && in_array($row['unit'], $units, true)
        && in_array($row['geography_level'], $geographyLevels, true);

    $expectedPage = $tableNumber === 6 ? 31 : (is_int($tableNumber) ? $tableNumber + 28 : 0);
    if (!is_int($tableNumber) || $tableNumber < 6 || $tableNumber > 33
        || !is_int($pageNumber) || $pageNumber !== $expectedPage
        || !$validLabels
    ) {
        fclose($handle);
        fail_nisr_import('CSV row ' . $line . ' has an invalid table, page, or observation label.');
    }

    $valueStatus = $row['value_status'];
    $rawValue = $row['raw_value'];
    $numericValue = null;
    if ($valueStatus === 'reported') {
        if (!preg_match('/^\d{1,10}(?:\.\d{1,4})?$/D', $row['value'])
            || str_replace(',', '', $rawValue) !== $row['value']
            || (float) $row['value'] < 0
            || (float) $row['value'] > 9999999999.9999
            || ($row['unit'] === 'Percentage' && (float) $row['value'] > 100)
        ) {
            fclose($handle);
            fail_nisr_import('CSV row ' . $line . ' has an invalid percentage value.');
        }
        $numericValue = $row['value'];
    } elseif ($valueStatus === 'dash') {
        if ($rawValue !== '-' || $row['value'] !== '') {
            fclose($handle);
            fail_nisr_import('CSV row ' . $line . ' has an invalid dash/missing-value marker.');
        }
    } elseif ($valueStatus === 'blank') {
        if ($rawValue !== '' || $row['value'] !== '') {
            fclose($handle);
            fail_nisr_import('CSV row ' . $line . ' has an invalid blank/missing-value marker.');
        }
    } elseif ($valueStatus === 'not_available') {
        if (!in_array($rawValue, ['NA', '(NA)'], true) || $row['value'] !== '') {
            fclose($handle);
            fail_nisr_import('CSV row ' . $line . ' has an invalid not-available marker.');
        }
    } else {
        fclose($handle);
        fail_nisr_import('CSV row ' . $line . ' has an unknown value status.');
    }

    $identity = json_encode([
        $tableNumber,
        $pageNumber,
        $row['geography_level'],
        $row['geography_name'],
        $row['indicator'],
        $row['subgroup'],
    ], JSON_THROW_ON_ERROR);
    $recordKey = hash('sha256', $identity);
    if (isset($seen[$recordKey])) {
        fclose($handle);
        fail_nisr_import('CSV contains duplicate observations at line ' . $line . '.');
    }
    $seen[$recordKey] = true;
    $rows[] = [
        'record_key' => $recordKey,
        'table_number' => $tableNumber,
        'table_title' => $row['table_title'],
        'source_pdf_page' => $pageNumber,
        'geography_level' => $row['geography_level'],
        'geography_name' => $row['geography_name'],
        'indicator' => $row['indicator'],
        'subgroup' => $row['subgroup'],
        'unit' => $row['unit'],
        'numeric_value' => $numericValue,
        'raw_value' => $rawValue,
        'value_status' => $valueStatus,
    ];
}
fclose($handle);

if ($rows === []) {
    fail_nisr_import('CSV contains no observations.');
}

$datasetTitle = 'Seasonal Agricultural Survey, Season B 2026';
$publisher = 'National Institute of Statistics of Rwanda (NISR)';
$releaseYear = 2026;
$citation = 'National Institute of Statistics of Rwanda (NISR), Seasonal Agricultural Survey, Season B, September 2026, Tables 6-33. Report states CC BY 4.0.';
$referenceUrl = 'https://www.statistics.gov.rw/';
$reuseTerms = 'CC BY 4.0, as stated in the report; attribution to NISR required.';
$nisrCropNames = [
    'Maize' => 'Maize',
    'Sorghum' => 'Sorghum',
    'Paddy rice' => 'Rice',
    'Cassava' => 'Cassava',
    'Sweet potatoes' => 'Sweet potato',
    'Irish potatoes' => 'Potato',
    'Cooking banana' => 'Plantain (matoke)',
    'Dessert banana' => 'Banana',
    'Beans' => 'Common beans',
    'Ground nuts' => 'Groundnut',
    'Soya beans' => 'Soybean',
];

try {
    $pdo = get_db_connection();
    $pdo->beginTransaction();
    $sourceQuery = $pdo->prepare(
        'SELECT id, citation, reference_url, reuse_terms
         FROM agricultural_data_sources
         WHERE dataset_title = :title AND publisher = :publisher AND release_year = :release_year'
    );
    $sourceQuery->execute([
        ':title' => $datasetTitle,
        ':publisher' => $publisher,
        ':release_year' => $releaseYear,
    ]);
    $source = $sourceQuery->fetch();
    if ($source !== false) {
        if ($source['citation'] !== $citation
            || $source['reference_url'] !== $referenceUrl
            || $source['reuse_terms'] !== $reuseTerms
        ) {
            throw new RuntimeException('Dataset metadata conflicts with its existing source registry entry.');
        }
        $sourceId = (int) $source['id'];
    } else {
        $sourceInsert = $pdo->prepare(
            'INSERT INTO agricultural_data_sources
                (dataset_title, publisher, release_year, citation, reference_url, reuse_terms, is_active_for_scoring)
             VALUES (:title, :publisher, :release_year, :citation, :url, :reuse_terms, 0)'
        );
        $sourceInsert->execute([
            ':title' => $datasetTitle,
            ':publisher' => $publisher,
            ':release_year' => $releaseYear,
            ':citation' => $citation,
            ':url' => $referenceUrl,
            ':reuse_terms' => $reuseTerms,
        ]);
        $sourceId = (int) $pdo->lastInsertId();
    }

    $activateSource = $pdo->prepare(
        'UPDATE agricultural_data_sources SET is_active_for_scoring = 1 WHERE id = :source_id'
    );
    $activateSource->execute([':source_id' => $sourceId]);

    foreach (array_chunk($rows, 250) as $batch) {
        $valueGroups = [];
        $parameters = [];
        foreach ($batch as $index => $row) {
            $prefix = ':r' . $index . '_';
            $valueGroups[] = '(' . implode(', ', [
                $prefix . 'source_id',
                $prefix . 'record_key',
                '2026',
                '\'B\'',
                $prefix . 'table_number',
                $prefix . 'table_title',
                $prefix . 'source_pdf_page',
                $prefix . 'geography_level',
                $prefix . 'geography_name',
                $prefix . 'indicator',
                $prefix . 'subgroup',
                $prefix . 'unit',
                $prefix . 'numeric_value',
                $prefix . 'raw_value',
                $prefix . 'value_status',
            ]) . ')';
            $parameters[$prefix . 'source_id'] = $sourceId;
            $parameters[$prefix . 'record_key'] = $row['record_key'];
            $parameters[$prefix . 'table_number'] = $row['table_number'];
            $parameters[$prefix . 'table_title'] = $row['table_title'];
            $parameters[$prefix . 'source_pdf_page'] = $row['source_pdf_page'];
            $parameters[$prefix . 'geography_level'] = $row['geography_level'];
            $parameters[$prefix . 'geography_name'] = $row['geography_name'];
            $parameters[$prefix . 'indicator'] = $row['indicator'];
            $parameters[$prefix . 'subgroup'] = $row['subgroup'];
            $parameters[$prefix . 'unit'] = $row['unit'];
            $parameters[$prefix . 'numeric_value'] = $row['numeric_value'];
            $parameters[$prefix . 'raw_value'] = $row['raw_value'];
            $parameters[$prefix . 'value_status'] = $row['value_status'];
        }

        $insert = $pdo->prepare(
            'INSERT INTO agricultural_survey_observations
                (data_source_id, record_key, survey_year, season, table_number, table_title,
                 source_pdf_page, geography_level, geography_name, indicator, subgroup,
                 unit, numeric_value, raw_value, value_status)
             VALUES ' . implode(', ', $valueGroups) . '
             ON DUPLICATE KEY UPDATE
                table_title = VALUES(table_title),
                source_pdf_page = VALUES(source_pdf_page),
                unit = VALUES(unit),
                numeric_value = VALUES(numeric_value),
                raw_value = VALUES(raw_value),
                value_status = VALUES(value_status),
                imported_at = CURRENT_TIMESTAMP'
        );
        $insert->execute($parameters);
    }

    $districtRows = $pdo->query('SELECT id, name FROM districts')->fetchAll();
    $districtIds = [];
    foreach ($districtRows as $district) {
        $districtIds[strtolower((string) $district['name'])] = (int) $district['id'];
    }
    $cropRows = $pdo->query('SELECT id, name FROM crops')->fetchAll();
    $cropIds = [];
    foreach ($cropRows as $crop) {
        $cropIds[strtolower((string) $crop['name'])] = (int) $crop['id'];
    }

    $pdo->prepare(
        'DELETE FROM district_crop_statistics
         WHERE data_source_id = :source_id AND season = \'B\' AND survey_year = 2026'
    )->execute([':source_id' => $sourceId]);

    $yieldRecords = [];
    foreach ($rows as $row) {
        if ($row['table_number'] !== 11
            || $row['geography_level'] !== 'district'
            || $row['unit'] !== 'Kilograms per hectare'
            || $row['value_status'] !== 'reported'
            || !isset($nisrCropNames[$row['subgroup']])
        ) {
            continue;
        }
        $districtKey = strtolower($row['geography_name']);
        $cropKey = strtolower($nisrCropNames[$row['subgroup']]);
        if (!isset($districtIds[$districtKey], $cropIds[$cropKey])) {
            continue;
        }
        if ($row['numeric_value'] === null) {
            throw new RuntimeException('A reported NISR yield observation has no numeric value.');
        }
        $yieldRecords[] = [
            'district_id' => $districtIds[$districtKey],
            'crop_id' => $cropIds[$cropKey],
            'yield' => $row['numeric_value'],
        ];
    }
    if ($yieldRecords === []) {
        throw new RuntimeException('No report Table 11 district yields could be mapped to the application crops.');
    }

    foreach (array_chunk($yieldRecords, 250) as $batch) {
        $valueGroups = [];
        $parameters = [];
        foreach ($batch as $index => $record) {
            $prefix = ':y' . $index . '_';
            $valueGroups[] = '(' . $prefix . 'district_id, ' . $prefix . 'crop_id, \'B\', 2026, '
                . 'NULL, NULL, NULL, ' . $prefix . 'yield, ' . $prefix . 'source_id)';
            $parameters[$prefix . 'district_id'] = $record['district_id'];
            $parameters[$prefix . 'crop_id'] = $record['crop_id'];
            $parameters[$prefix . 'yield'] = $record['yield'];
            $parameters[$prefix . 'source_id'] = $sourceId;
        }
        $yieldInsert = $pdo->prepare(
            'INSERT INTO district_crop_statistics
                (district_id, crop_id, season, survey_year, cultivated_area_ha, harvested_area_ha,
                 production_tonnes, published_yield_kg_per_hectare, data_source_id)
             VALUES ' . implode(', ', $valueGroups) . '
             ON DUPLICATE KEY UPDATE
                cultivated_area_ha = NULL,
                harvested_area_ha = NULL,
                production_tonnes = NULL,
                published_yield_kg_per_hectare = VALUES(published_yield_kg_per_hectare),
                imported_at = CURRENT_TIMESTAMP'
        );
        $yieldInsert->execute($parameters);
    }

    $pdo->commit();
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('NISR SAS observation import failed: ' . $error->getMessage());
    fail_nisr_import('Import failed and was rolled back: ' . $error->getMessage());
}

fwrite(
    STDOUT,
    'Imported ' . count($rows) . ' reference observations and ' . count($yieldRecords) .
    ' district crop-yield records from ' . $datasetTitle . '; NISR is selected for scoring.' . PHP_EOL
);
