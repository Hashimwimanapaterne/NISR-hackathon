<?php
/**
 * Import an aggregated district/season crop CSV with an auditable citation.
 *
 * Usage:
 * php bin/import_district_crop_stats.php <csv> <dataset-title> <release-year> \
 *   <publisher> <citation> <reference-url> <reuse-terms>
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';

const MAX_IMPORT_BYTES = 25 * 1024 * 1024;
const MAX_IMPORT_ROWS = 10000;

function fail_import(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

if ($argc !== 8) {
    fail_import(
        "Usage: php bin/import_district_crop_stats.php <csv> <dataset-title> <release-year> " .
        "<publisher> <citation> <reference-url> <reuse-terms>"
    );
}

$csvPath = $argv[1];
$datasetTitle = trim($argv[2]);
$releaseYear = filter_var($argv[3], FILTER_VALIDATE_INT);
$publisher = trim($argv[4]);
$citation = trim($argv[5]);
$referenceUrl = trim($argv[6]);
$reuseTerms = trim($argv[7]);

if (!is_file($csvPath) || !is_readable($csvPath)) {
    fail_import('The CSV file does not exist or cannot be read.');
}
$fileSize = filesize($csvPath);
if ($fileSize === false || $fileSize > MAX_IMPORT_BYTES) {
    fail_import('CSV file exceeds the 25 MB import limit.');
}
if (!is_int($releaseYear) || $releaseYear < 1900 || $releaseYear > (int) date('Y')) {
    fail_import('Dataset release year must be a valid year no later than the current year.');
}
if ($datasetTitle === '' || strlen($datasetTitle) > 255
    || $publisher === '' || strlen($publisher) > 160
    || $citation === '' || strlen($citation) > 500
    || $reuseTerms === '' || strlen($reuseTerms) > 500
) {
    fail_import('Dataset title, publisher, citation, and reuse terms are required and must fit their documented limits.');
}
if (strlen($referenceUrl) > 500
    || filter_var($referenceUrl, FILTER_VALIDATE_URL) === false
    || !in_array(strtolower((string) parse_url($referenceUrl, PHP_URL_SCHEME)), ['http', 'https'], true)
) {
    fail_import('Provide a valid HTTP or HTTPS dataset reference URL.');
}

$handle = fopen($csvPath, 'rb');
if ($handle === false) {
    fail_import('Could not open the CSV file.');
}

$expectedHeaders = [
    'district',
    'season',
    'survey_year',
    'crop_name',
    'cultivated_area_ha',
    'production_tonnes',
];
$extendedHeaders = [
    'district',
    'season',
    'survey_year',
    'crop_name',
    'cultivated_area_ha',
    'harvested_area_ha',
    'production_tonnes',
    'yield_kg_per_hectare',
];
$headers = fgetcsv($handle, null, ',', '"', '');
if (!is_array($headers)) {
    fclose($handle);
    fail_import('CSV is empty or has an invalid header row.');
}
$headers = array_map(static function ($header): string {
    return trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $header));
}, $headers);
if (count($headers) !== count(array_unique($headers))) {
    fclose($handle);
    fail_import('CSV column headers must not be duplicated.');
}
if ($headers === $expectedHeaders) {
    $hasExtendedYieldFields = false;
} elseif ($headers === $extendedHeaders) {
    $hasExtendedYieldFields = true;
} else {
    fclose($handle);
    fail_import(
        "CSV headers must be exactly either:\n" . implode(',', $expectedHeaders) .
        "\nor:\n" . implode(',', $extendedHeaders)
    );
}
$columnIndex = array_flip($headers);

$pdo = get_db_connection();
$districtRows = $pdo->query('SELECT id, name FROM districts')->fetchAll();
$cropRows = $pdo->query('SELECT id, name FROM crops')->fetchAll();
$districtIds = [];
foreach ($districtRows as $district) {
    $districtIds[strtolower((string) $district['name'])] = (int) $district['id'];
}
$cropIds = [];
foreach ($cropRows as $crop) {
    $cropIds[strtolower((string) $crop['name'])] = (int) $crop['id'];
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
        fail_import('CSV exceeds the 10,000-row import limit.');
    }
    if (count($record) !== count($headers)) {
        fclose($handle);
        fail_import('CSV row ' . $line . ' has the wrong number of columns.');
    }

    $districtName = trim((string) $record[$columnIndex['district']]);
    $season = strtoupper(trim((string) $record[$columnIndex['season']]));
    $surveyYear = filter_var(trim((string) $record[$columnIndex['survey_year']]), FILTER_VALIDATE_INT);
    $cropName = trim((string) $record[$columnIndex['crop_name']]);
    $area = trim((string) $record[$columnIndex['cultivated_area_ha']]);
    $production = trim((string) $record[$columnIndex['production_tonnes']]);
    $harvestedArea = $hasExtendedYieldFields
        ? trim((string) $record[$columnIndex['harvested_area_ha']])
        : '';
    $publishedYield = $hasExtendedYieldFields
        ? trim((string) $record[$columnIndex['yield_kg_per_hectare']])
        : '';
    $areaValue = is_numeric($area) ? (float) $area : 0.0;
    $productionValue = is_numeric($production) ? (float) $production : -1.0;
    $harvestedAreaValue = $harvestedArea === '' ? null : (is_numeric($harvestedArea) ? (float) $harvestedArea : -1.0);
    $publishedYieldValue = $publishedYield === '' ? null : (is_numeric($publishedYield) ? (float) $publishedYield : -1.0);
    $districtKey = strtolower($districtName);
    $cropKey = strtolower($cropName);

    if (!isset($districtIds[$districtKey])) {
        fclose($handle);
        fail_import('CSV row ' . $line . ' references an unknown Rwanda district: ' . $districtName);
    }
    if (!isset($cropIds[$cropKey])) {
        fclose($handle);
        fail_import('CSV row ' . $line . ' references an unknown crop: ' . $cropName);
    }
    if (!in_array($season, ['A', 'B', 'C'], true)
        || !is_int($surveyYear)
        || $surveyYear < 1900
        || $surveyYear > (int) date('Y')
    ) {
        fclose($handle);
        fail_import('CSV row ' . $line . ' has an invalid season or survey year.');
    }
    if (!is_finite($areaValue) || $areaValue <= 0
        || !is_finite($productionValue) || $productionValue < 0
        || ($harvestedAreaValue !== null && (!is_finite($harvestedAreaValue) || $harvestedAreaValue <= 0))
        || ($publishedYieldValue !== null && (!is_finite($publishedYieldValue) || $publishedYieldValue < 0))
    ) {
        fclose($handle);
        fail_import('CSV row ' . $line . ' contains an invalid area, production, or published yield value.');
    }

    $districtId = $districtIds[$districtKey];
    $cropId = $cropIds[$cropKey];
    $rowKey = implode(':', [$districtId, $cropId, $season, $surveyYear]);
    if (isset($seen[$rowKey])) {
        fclose($handle);
        fail_import('CSV contains duplicate district/crop/season/year rows at line ' . $line . '.');
    }
    $seen[$rowKey] = true;
    $rows[] = [
        'district_id' => $districtId,
        'crop_id' => $cropId,
        'season' => $season,
        'survey_year' => $surveyYear,
        'area' => $areaValue,
        'harvested_area' => $harvestedAreaValue,
        'production' => $productionValue,
        'published_yield' => $publishedYieldValue,
    ];
}
fclose($handle);

if ($rows === []) {
    fail_import('CSV has a header but no aggregated crop observations.');
}

try {
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
            throw new RuntimeException('Dataset metadata conflicts with the existing source registry entry.');
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

    $insert = $pdo->prepare(
        'INSERT INTO district_crop_statistics
            (district_id, crop_id, season, survey_year, cultivated_area_ha, harvested_area_ha,
             production_tonnes, published_yield_kg_per_hectare, data_source_id)
         VALUES (:district_id, :crop_id, :season, :survey_year, :area, :harvested_area,
                 :production, :published_yield, :source_id)
         ON DUPLICATE KEY UPDATE
            cultivated_area_ha = VALUES(cultivated_area_ha),
            harvested_area_ha = VALUES(harvested_area_ha),
            production_tonnes = VALUES(production_tonnes),
            published_yield_kg_per_hectare = VALUES(published_yield_kg_per_hectare),
            imported_at = CURRENT_TIMESTAMP'
    );
    foreach ($rows as $row) {
        $insert->execute([
            ':district_id' => $row['district_id'],
            ':crop_id' => $row['crop_id'],
            ':season' => $row['season'],
            ':survey_year' => $row['survey_year'],
            ':area' => $row['area'],
            ':harvested_area' => $row['harvested_area'],
            ':production' => $row['production'],
            ':published_yield' => $row['published_yield'],
            ':source_id' => $sourceId,
        ]);
    }
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('District crop statistics import failed: ' . $error->getMessage());
    fail_import('Import failed and was rolled back: ' . $error->getMessage());
}

fwrite(STDOUT, 'Imported ' . count($rows) . ' district-season-crop observations from ' .
    $datasetTitle . ' (' . $releaseYear . ').' . PHP_EOL);
