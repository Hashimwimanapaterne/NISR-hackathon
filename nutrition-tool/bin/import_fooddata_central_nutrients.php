<?php
/**
 * Discover and import reviewed USDA FoodData Central nutrient profiles.
 *
 * Usage:
 *   php bin/import_fooddata_central_nutrients.php discover [candidates.csv]
 *   php bin/import_fooddata_central_nutrients.php import <reviewed.csv>
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';

const FDC_API_BASE = 'https://api.nal.usda.gov/fdc/v1';
const FDC_CITATION = 'USDA FoodData Central, CC0 1.0';
const FDC_CANDIDATE_HEADERS = [
    'food_name',
    'fdc_id',
    'description',
    'data_type',
    'preparation_state',
    'approved',
];

function fail_fdc_import(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function fdc_api_key(): string
{
    $apiKey = getenv('USDA_FDC_API_KEY');
    if (!is_string($apiKey) || trim($apiKey) === '') {
        throw new RuntimeException(
            'Set USDA_FDC_API_KEY in nutrition-tool/.env before contacting FoodData Central.'
        );
    }

    return trim($apiKey);
}

function fdc_request(string $path, string $apiKey, ?array $payload = null): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('FoodData Central imports require the PHP cURL extension.');
    }

    $url = FDC_API_BASE . $path . '?' . http_build_query(['api_key' => $apiKey]);
    $handle = curl_init($url);
    if ($handle === false) {
        throw new RuntimeException('Could not initialize the FoodData Central request.');
    }

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'UmurimaData Nutrient Importer/1.0',
    ];
    if ($payload !== null) {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_HTTPHEADER] = ['Content-Type: application/json'];
        $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_THROW_ON_ERROR);
    }
    curl_setopt_array($handle, $options);
    $body = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $curlError = curl_error($handle);
    curl_close($handle);

    if ($body === false) {
        throw new RuntimeException('FoodData Central request failed: ' . $curlError);
    }
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException(
            'FoodData Central returned HTTP ' . $status .
            '. Check the API key, rate limit, and FDC record ID.'
        );
    }

    try {
        $decoded = json_decode((string) $body, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        throw new RuntimeException('FoodData Central returned invalid JSON.', 0, $error);
    }
    if (!is_array($decoded)) {
        throw new RuntimeException('FoodData Central returned an unexpected response.');
    }

    return $decoded;
}

function fdc_search_query(string $foodName): string
{
    $queries = [
        'White rice' => 'rice white long grain regular raw unenriched',
        'Maize flour' => 'corn flour yellow whole grain unenriched raw',
        'Sorghum flour' => 'sorghum flour',
        'Irish potatoes' => 'potatoes flesh raw',
        'Sweet potatoes' => 'sweet potato orange flesh raw',
        'Cassava (fresh)' => 'cassava raw',
        'Cassava flour' => 'cassava flour',
        'Dry beans (mixed)' => 'beans mature seeds dry raw',
        'Groundnuts (shelled)' => 'peanuts raw shelled',
        'Soybeans' => 'soybeans mature seeds raw',
        'Green bananas (matoke)' => 'plantains green raw',
        'Ripe bananas' => 'bananas raw',
        'Avocado' => 'avocado raw',
        'Tomatoes' => 'tomatoes red ripe raw',
        'Onions' => 'onions raw',
        'Cabbage' => 'cabbage raw',
        'Carrots' => 'carrots raw',
        'Dodo (amaranth greens)' => 'amaranth leaves raw',
        'Eggs' => 'egg whole raw',
        'Milk (fresh)' => 'milk whole fluid raw',
        'Dry fish (indagara)' => 'indagara dried fish',
        'Beef' => 'beef ground raw',
        'Chicken' => 'chicken broilers fryers meat skin raw',
        'Cooking oil' => 'cooking oil',
        'Sugar' => 'sugar granulated',
    ];

    return $queries[$foodName] ?? $foodName;
}

function write_csv(string $path, array $headers, array $rows): void
{
    $handle = fopen($path, 'wb');
    if ($handle === false) {
        throw new RuntimeException('Could not write candidate CSV: ' . $path);
    }

    if (fputcsv($handle, $headers, ',', '"', '') === false) {
        fclose($handle);
        throw new RuntimeException('Could not write candidate CSV header.');
    }
    foreach ($rows as $row) {
        if (fputcsv($handle, $row, ',', '"', '') === false) {
            fclose($handle);
            throw new RuntimeException('Could not write all candidate CSV rows.');
        }
    }
    fclose($handle);
}

function discover_foods(PDO $pdo, string $apiKey, string $outputPath): void
{
    $foods = $pdo->query('SELECT name FROM foods WHERE is_active = 1 ORDER BY name')->fetchAll();
    $candidates = [];
    foreach ($foods as $food) {
        $foodName = (string) $food['name'];
        $response = fdc_request('/foods/search', $apiKey, [
            'query' => fdc_search_query($foodName),
            'dataType' => ['Foundation', 'SR Legacy'],
            'pageSize' => 10,
        ]);
        foreach (($response['foods'] ?? []) as $candidate) {
            if (!is_array($candidate)
                || !isset($candidate['fdcId'], $candidate['description'], $candidate['dataType'])
                || !in_array($candidate['dataType'], ['Foundation', 'SR Legacy'], true)
            ) {
                continue;
            }
            $candidates[] = [
                $foodName,
                (string) $candidate['fdcId'],
                (string) $candidate['description'],
                (string) $candidate['dataType'],
                '',
                '0',
            ];
        }
    }

    write_csv($outputPath, FDC_CANDIDATE_HEADERS, $candidates);
    fwrite(
        STDOUT,
        'Wrote ' . count($candidates) . ' USDA candidates for ' . count($foods) .
        ' catalog foods to ' . $outputPath . PHP_EOL .
        'Review descriptions and preparation states. Set approved=1 only for a suitable match.' . PHP_EOL
    );
}

function read_reviewed_mappings(string $path, PDO $pdo): array
{
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('Reviewed mapping CSV does not exist or cannot be read.');
    }
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException('Could not open the reviewed mapping CSV.');
    }
    $headers = fgetcsv($handle, null, ',', '"', '');
    if (!is_array($headers)) {
        fclose($handle);
        throw new RuntimeException('Reviewed mapping CSV is empty.');
    }
    $headers = array_map(
        static fn ($value): string => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $value)),
        $headers
    );
    if ($headers !== FDC_CANDIDATE_HEADERS) {
        fclose($handle);
        throw new RuntimeException(
            'Reviewed mapping CSV headers must be: ' . implode(',', FDC_CANDIDATE_HEADERS)
        );
    }

    $catalogRows = $pdo->query('SELECT id, name FROM foods WHERE is_active = 1')->fetchAll();
    $catalog = [];
    foreach ($catalogRows as $row) {
        $catalog[(string) $row['name']] = (int) $row['id'];
    }

    $approved = [];
    $line = 1;
    while (($record = fgetcsv($handle, null, ',', '"', '')) !== false) {
        $line++;
        if ($record === [null] || $record === []) {
            continue;
        }
        if (count($record) !== count(FDC_CANDIDATE_HEADERS)) {
            fclose($handle);
            throw new RuntimeException('CSV row ' . $line . ' has the wrong number of columns.');
        }
        $row = array_combine(FDC_CANDIDATE_HEADERS, array_map('trim', $record));
        if (!is_array($row) || !in_array($row['approved'], ['0', '1'], true)) {
            fclose($handle);
            throw new RuntimeException('CSV row ' . $line . ' has an invalid approved value.');
        }
        if ($row['approved'] !== '1') {
            continue;
        }
        if (!isset($catalog[$row['food_name']])) {
            fclose($handle);
            throw new RuntimeException('CSV row ' . $line . ' names an unknown catalog food.');
        }
        if (!preg_match('/^\d{1,10}$/D', $row['fdc_id'])
            || $row['description'] === ''
            || strlen($row['description']) > 255
            || !in_array($row['data_type'], ['Foundation', 'SR Legacy'], true)
            || $row['preparation_state'] === ''
            || strlen($row['preparation_state']) > 80
        ) {
            fclose($handle);
            throw new RuntimeException('CSV row ' . $line . ' has invalid approved mapping details.');
        }
        if (isset($approved[$row['food_name']])) {
            fclose($handle);
            throw new RuntimeException('Only one FDC record may be approved for each food.');
        }
        $approved[$row['food_name']] = [
            'food_id' => $catalog[$row['food_name']],
            'fdc_id' => (int) $row['fdc_id'],
            'description' => $row['description'],
            'data_type' => $row['data_type'],
            'preparation_state' => $row['preparation_state'],
        ];
    }
    fclose($handle);

    if ($approved === []) {
        throw new RuntimeException('No mappings are approved; no nutrient profiles were imported.');
    }

    return $approved;
}

function fdc_nutrient_specs(): array
{
    return [
        '208' => ['column' => 'calories_kcal', 'unit' => 'KCAL'],
        '203' => ['column' => 'protein_g', 'unit' => 'G'],
        '204' => ['column' => 'fat_g', 'unit' => 'G'],
        '205' => ['column' => 'carbohydrates_g', 'unit' => 'G'],
        '291' => ['column' => 'fiber_g', 'unit' => 'G'],
        '303' => ['column' => 'iron_mg', 'unit' => 'MG'],
        '309' => ['column' => 'zinc_mg', 'unit' => 'MG'],
        '301' => ['column' => 'calcium_mg', 'unit' => 'MG'],
        '306' => ['column' => 'potassium_mg', 'unit' => 'MG'],
        '320' => ['column' => 'vitamin_a_ug', 'unit' => 'UG'],
        '401' => ['column' => 'vitamin_c_mg', 'unit' => 'MG'],
        '417' => ['column' => 'folate_ug', 'unit' => 'UG'],
        '418' => ['column' => 'vitamin_b12_ug', 'unit' => 'UG'],
    ];
}

function extract_fdc_nutrients(array $food): array
{
    $specs = fdc_nutrient_specs();
    $values = [];
    foreach (($food['foodNutrients'] ?? []) as $foodNutrient) {
        if (!is_array($foodNutrient)) {
            continue;
        }
        $nutrient = $foodNutrient['nutrient'] ?? null;
        if (!is_array($nutrient)) {
            continue;
        }
        $number = (string) ($nutrient['number'] ?? '');
        if (!isset($specs[$number])) {
            continue;
        }
        $column = $specs[$number]['column'];
        $amount = $foodNutrient['amount'] ?? null;
        $unit = strtoupper((string) ($nutrient['unitName'] ?? ''));
        $unit = str_replace(['µG', 'μG', 'ΜG'], 'UG', $unit);
        if (!is_numeric($amount) || !is_finite((float) $amount) || (float) $amount < 0) {
            continue;
        }
        if ($unit !== $specs[$number]['unit']) {
            throw new RuntimeException(
                'FDC nutrient ' . $number . ' has unexpected unit "' . $unit . '".'
            );
        }
        if (array_key_exists($column, $values) && $values[$column] !== (float) $amount) {
            throw new RuntimeException('FDC record has conflicting values for nutrient ' . $number . '.');
        }
        $values[$column] = (float) $amount;
    }

    $missing = array_values(array_diff(
        array_column($specs, 'column'),
        array_keys($values)
    ));
    if ($missing !== []) {
        throw new RuntimeException(
            'FDC record is missing required nutrients: ' . implode(', ', $missing)
        );
    }

    return $values;
}

function import_reviewed_mappings(PDO $pdo, string $apiKey, array $mappings): void
{
    $profiles = [];
    foreach ($mappings as $foodName => $mapping) {
        $food = fdc_request('/food/' . $mapping['fdc_id'], $apiKey);
        $actualDescription = trim((string) ($food['description'] ?? ''));
        $actualDataType = (string) ($food['dataType'] ?? '');
        if ((int) ($food['fdcId'] ?? 0) !== $mapping['fdc_id']
            || strcasecmp($actualDescription, $mapping['description']) !== 0
            || $actualDataType !== $mapping['data_type']
        ) {
            throw new RuntimeException(
                'FDC ID ' . $mapping['fdc_id'] . ' no longer matches the reviewed description/type for ' .
                $foodName . '. Run discover again and review the mapping.'
            );
        }

        $profiles[] = [
            'food_name' => $foodName,
            'food_id' => $mapping['food_id'],
            'fdc_id' => $mapping['fdc_id'],
            'preparation_state' => $mapping['preparation_state'],
            'source_note' => FDC_CITATION . '; FDC ID ' . $mapping['fdc_id'] .
                '; ' . $mapping['data_type'] . '; https://fdc.nal.usda.gov/food-details/' .
                $mapping['fdc_id'] . '/nutrients',
            'nutrients' => extract_fdc_nutrients($food),
        ];
    }

    $nutrientColumns = array_column(fdc_nutrient_specs(), 'column');
    $columnList = implode(', ', $nutrientColumns);
    $placeholders = implode(', ', array_map(
        static fn (string $column): string => ':' . $column,
        $nutrientColumns
    ));
    $updateList = implode(', ', array_map(
        static fn (string $column): string => $column . ' = VALUES(' . $column . ')',
        $nutrientColumns
    ));
    $upsert = $pdo->prepare(
        'INSERT INTO nutrient_profiles
            (food_id, ' . $columnList . ', preparation_state, source_note)
         VALUES (:food_id, ' . $placeholders . ', :preparation_state, :source_note)
         ON DUPLICATE KEY UPDATE ' . $updateList . ',
            preparation_state = VALUES(preparation_state),
            source_note = VALUES(source_note)'
    );
    $existing = $pdo->prepare('SELECT source_note FROM nutrient_profiles WHERE food_id = :food_id');

    $pdo->beginTransaction();
    try {
        $imported = 0;
        $skipped = [];
        foreach ($profiles as $profile) {
            $existing->execute([':food_id' => $profile['food_id']]);
            $sourceNote = $existing->fetchColumn();
            if ($sourceNote !== false
                && !str_starts_with((string) $sourceNote, FDC_CITATION)
                && !str_starts_with((string) $sourceNote, 'Illustrative seed estimate')
            ) {
                $skipped[] = $profile['food_name'] . ' (existing non-FDC source retained)';
                continue;
            }

            $bindings = [
                ':food_id' => $profile['food_id'],
                ':preparation_state' => $profile['preparation_state'],
                ':source_note' => $profile['source_note'],
            ];
            foreach ($profile['nutrients'] as $column => $value) {
                $bindings[':' . $column] = $value;
            }
            $upsert->execute($bindings);
            $imported++;
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }

    fwrite(STDOUT, 'Imported or updated ' . $imported . ' USDA FoodData Central profiles.' . PHP_EOL);
    foreach ($skipped as $message) {
        fwrite(STDOUT, 'Skipped: ' . $message . PHP_EOL);
    }
}

try {
    if ($argc < 2 || !in_array($argv[1], ['discover', 'import'], true)) {
        fail_fdc_import(
            "Usage:\n" .
            "  php bin/import_fooddata_central_nutrients.php discover [candidates.csv]\n" .
            "  php bin/import_fooddata_central_nutrients.php import <reviewed.csv>"
        );
    }
    $pdo = get_db_connection();
    $apiKey = fdc_api_key();

    if ($argv[1] === 'discover') {
        if ($argc > 3) {
            fail_fdc_import('Discover accepts at most one output CSV path.');
        }
        $outputPath = $argv[2] ?? (__DIR__ . '/../data/fdc_nutrient_candidates.csv');
        discover_foods($pdo, $apiKey, $outputPath);
        exit(0);
    }

    if ($argc !== 3) {
        fail_fdc_import('Import requires a reviewed mapping CSV path.');
    }
    $mappings = read_reviewed_mappings($argv[2], $pdo);
    import_reviewed_mappings($pdo, $apiKey, $mappings);
} catch (Throwable $error) {
    error_log('FoodData Central nutrient import failed: ' . $error->getMessage());
    fail_fdc_import('Import failed: ' . $error->getMessage());
}
