<?php
/**
 * Import mapped NISR SAS Season A 2026 district yields for crop scoring.
 *
 * Usage: php bin/import_nisr_sas_2026a_yields.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';

const NISR_SAS_2026A_CSV = __DIR__ . '/../data/nisr_sas_2026a_district_yields.csv';
const NISR_SAS_2026A_TITLE = 'Seasonal Agricultural Survey, Season A 2026 - Table 13 district crop yields';
const NISR_PUBLISHER_NAME = 'National Institute of Statistics of Rwanda (NISR)';
const NISR_SAS_2026A_CITATION = 'National Institute of Statistics of Rwanda (NISR), Seasonal Agricultural Survey, Season A, March 2026, Table 13, PDF p. 35.';
const NISR_SAS_2026A_URL = 'https://www.statistics.gov.rw/';
const NISR_SAS_2026A_LICENSE = 'CC BY 4.0; attribute NISR and cite the report.';

function fail_nisr_sas_2026a_import(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

if (!is_file(NISR_SAS_2026A_CSV) || !is_readable(NISR_SAS_2026A_CSV)) {
    fail_nisr_sas_2026a_import('The NISR SAS 2026A district-yield CSV is missing or unreadable.');
}

$handle = fopen(NISR_SAS_2026A_CSV, 'rb');
if ($handle === false) {
    fail_nisr_sas_2026a_import('Could not open the NISR SAS 2026A district-yield CSV.');
}

$expectedHeaders = [
    'report_year',
    'season',
    'district',
    'crop_as_reported',
    'crop_for_app',
    'yield_kg_per_ha',
    'raw_value',
    'value_status',
    'source_publisher',
    'source_report',
    'table_number',
    'table_title',
    'source_pdf_page',
    'measurement_unit',
];
$headers = fgetcsv($handle, null, ',', '"', '');
$headers = is_array($headers)
    ? array_map(static fn($header): string => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $header)), $headers)
    : [];
if ($headers !== $expectedHeaders) {
    fclose($handle);
    fail_nisr_sas_2026a_import('The NISR SAS 2026A CSV has an unexpected header or column order.');
}

$districtNames = [];
$cropNames = [];
$pdo = get_db_connection();
foreach ($pdo->query('SELECT id, name FROM districts')->fetchAll() as $district) {
    $districtNames[strtolower((string) $district['name'])] = (int) $district['id'];
}
foreach ($pdo->query('SELECT id, name FROM crops')->fetchAll() as $crop) {
    $cropNames[strtolower((string) $crop['name'])] = (int) $crop['id'];
}

$observations = [];
$seen = [];
$sourceRows = 0;
$lineNumber = 1;
while (($record = fgetcsv($handle, null, ',', '"', '')) !== false) {
    $lineNumber++;
    if ($record === [null] || $record === []) {
        continue;
    }
    if (count($record) !== count($expectedHeaders)) {
        fclose($handle);
        fail_nisr_sas_2026a_import('CSV row ' . $lineNumber . ' has the wrong number of columns.');
    }
    $row = array_combine($expectedHeaders, $record);
    if (!is_array($row)) {
        fclose($handle);
        fail_nisr_sas_2026a_import('CSV row ' . $lineNumber . ' could not be read.');
    }
    $sourceRows++;
    if ($row['report_year'] !== '2026' || strtoupper($row['season']) !== 'A'
        || $row['source_publisher'] !== NISR_PUBLISHER_NAME
        || $row['table_number'] !== '13' || $row['source_pdf_page'] !== '35'
        || $row['measurement_unit'] !== 'kg/ha'
    ) {
        fclose($handle);
        fail_nisr_sas_2026a_import('CSV row ' . $lineNumber . ' does not match the expected NISR source metadata.');
    }

    $cropName = trim((string) $row['crop_for_app']);
    $yield = trim((string) $row['yield_kg_per_ha']);
    if ($cropName === '' || $row['value_status'] !== 'reported' || $yield === '') {
        if ($row['value_status'] === 'reported' && $yield === '') {
            fclose($handle);
            fail_nisr_sas_2026a_import('Reported yield is missing on CSV row ' . $lineNumber . '.');
        }
        continue;
    }
    if (!is_numeric($yield) || (float) $yield < 0 || !is_finite((float) $yield)) {
        fclose($handle);
        fail_nisr_sas_2026a_import('CSV row ' . $lineNumber . ' has an invalid reported yield.');
    }

    $districtKey = strtolower(trim((string) $row['district']));
    $cropKey = strtolower($cropName);
    if (!isset($districtNames[$districtKey])) {
        fclose($handle);
        fail_nisr_sas_2026a_import('CSV row ' . $lineNumber . ' has an unknown district: ' . $row['district']);
    }
    if (!isset($cropNames[$cropKey])) {
        fclose($handle);
        fail_nisr_sas_2026a_import('CSV row ' . $lineNumber . ' has an unmapped app crop: ' . $cropName);
    }

    $key = implode(':', [$districtNames[$districtKey], $cropNames[$cropKey]]);
    if (isset($seen[$key])) {
        fclose($handle);
        fail_nisr_sas_2026a_import('CSV contains duplicate mapped district/crop yields near row ' . $lineNumber . '.');
    }
    $seen[$key] = true;
    $observations[] = [
        'district_id' => $districtNames[$districtKey],
        'crop_id' => $cropNames[$cropKey],
        'yield' => (float) $yield,
    ];
}
fclose($handle);

if ($sourceRows !== 690 || count($observations) !== 288) {
    fail_nisr_sas_2026a_import(
        'Expected all 690 district/crop cells and 288 mapped, reported yields; found ' .
        $sourceRows . ' rows and ' . count($observations) . ' importable observations.'
    );
}

try {
    $pdo->beginTransaction();
    $sourceQuery = $pdo->prepare(
        'SELECT id, citation, reference_url, reuse_terms
         FROM agricultural_data_sources
         WHERE dataset_title = :title AND publisher = :publisher AND release_year = 2026'
    );
    $sourceQuery->execute([
        ':title' => NISR_SAS_2026A_TITLE,
        ':publisher' => NISR_PUBLISHER_NAME,
    ]);
    $source = $sourceQuery->fetch();
    if ($source !== false) {
        if ($source['citation'] !== NISR_SAS_2026A_CITATION
            || $source['reference_url'] !== NISR_SAS_2026A_URL
            || $source['reuse_terms'] !== NISR_SAS_2026A_LICENSE
        ) {
            throw new RuntimeException('NISR SAS 2026A source metadata conflicts with the existing registry entry.');
        }
        $sourceId = (int) $source['id'];
    } else {
        $insertSource = $pdo->prepare(
            'INSERT INTO agricultural_data_sources
                (dataset_title, publisher, release_year, citation, reference_url, reuse_terms, is_active_for_scoring)
             VALUES (:title, :publisher, 2026, :citation, :url, :reuse_terms, 0)'
        );
        $insertSource->execute([
            ':title' => NISR_SAS_2026A_TITLE,
            ':publisher' => NISR_PUBLISHER_NAME,
            ':citation' => NISR_SAS_2026A_CITATION,
            ':url' => NISR_SAS_2026A_URL,
            ':reuse_terms' => NISR_SAS_2026A_LICENSE,
        ]);
        $sourceId = (int) $pdo->lastInsertId();
    }

    $delete = $pdo->prepare(
        'DELETE FROM district_crop_statistics
         WHERE data_source_id = :source_id AND season = :season AND survey_year = :survey_year'
    );
    $delete->execute([
        ':source_id' => $sourceId,
        ':season' => 'A',
        ':survey_year' => 2026,
    ]);

    $insert = $pdo->prepare(
        'INSERT INTO district_crop_statistics
            (district_id, crop_id, season, survey_year, cultivated_area_ha, harvested_area_ha,
             production_tonnes, published_yield_kg_per_hectare, data_source_id)
         VALUES (:district_id, :crop_id, :season, :survey_year, NULL, NULL, NULL, :yield, :source_id)'
    );
    foreach ($observations as $observation) {
        $insert->execute([
            ':district_id' => $observation['district_id'],
            ':crop_id' => $observation['crop_id'],
            ':season' => 'A',
            ':survey_year' => 2026,
            ':yield' => $observation['yield'],
            ':source_id' => $sourceId,
        ]);
    }
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('NISR SAS 2026A yield import failed: ' . $error->getMessage());
    fail_nisr_sas_2026a_import('Import failed and was rolled back: ' . $error->getMessage());
}

fwrite(
    STDOUT,
    'Imported ' . count($observations) . ' mapped district/crop yields from NISR SAS Season A 2026. ' .
    'Unmapped crop groups and unreported cells were not imported.' . PHP_EOL
);
