<?php
/**
 * Import recent, unit-compatible WFP retail prices for Rwanda.
 *
 * Usage:
 *   php bin/import_wfp_rwanda_prices.php [path-to-wfp_food_prices_rwa.csv]
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';

const WFP_RWA_URL = 'https://data.humdata.org/dataset/a4a84c1c-81d1-491b-9fbe-1955ae736508/resource/8c22eeb5-cc2e-46bc-8a0d-08b7486b2486/download/wfp_food_prices_rwa.csv';
const WFP_RWA_REFERENCE = 'WFP Price Database via HDX, Rwanda food prices, CC BY-IGO: https://data.humdata.org/dataset/a4a84c1c-81d1-491b-9fbe-1955ae736508';
const WFP_FOOD_MAPPINGS = [
    'Beans (dry)' => ['Dry beans (mixed)', 'KG', 'kg'],
    'Cabbage' => ['Cabbage', 'KG', 'kg'],
    'Cassava' => ['Cassava (fresh)', 'KG', 'kg'],
    'Cassava flour' => ['Cassava flour', 'KG', 'kg'],
    'Maize flour' => ['Maize flour', 'KG', 'kg'],
    'Meat (beef)' => ['Beef', 'KG', 'kg'],
    'Oil' => ['Cooking oil', 'L', 'litre'],
    'Potatoes (Irish)' => ['Irish potatoes', 'KG', 'kg'],
    'Rice (local)' => ['White rice', 'KG', 'kg'],
    'Sugar' => ['Sugar', 'KG', 'kg'],
];

function wfp_download_csv(): string
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('WFP imports require the PHP cURL extension.');
    }

    $path = tempnam(sys_get_temp_dir(), 'wfp-rwanda-');
    if ($path === false) {
        throw new RuntimeException('Could not create a temporary WFP download file.');
    }
    $file = fopen($path, 'wb');
    if ($file === false) {
        unlink($path);
        throw new RuntimeException('Could not open the temporary WFP download file.');
    }

    $handle = curl_init(WFP_RWA_URL);
    if ($handle === false) {
        fclose($file);
        unlink($path);
        throw new RuntimeException('Could not initialize the WFP download.');
    }
    curl_setopt_array($handle, [
        CURLOPT_FILE => $file,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 180,
        CURLOPT_USERAGENT => 'UmurimaData WFP Rwanda Price Importer/1.0',
    ]);
    $success = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $curlError = curl_error($handle);
    curl_close($handle);
    fclose($file);

    if ($success === false || $status < 200 || $status >= 300) {
        unlink($path);
        throw new RuntimeException(
            'WFP download failed' . ($success === false ? ': ' . $curlError : ' with HTTP ' . $status) . '.'
        );
    }

    return $path;
}

function import_wfp_prices(PDO $pdo, string $csvPath): array
{
    if (!is_file($csvPath) || !is_readable($csvPath)) {
        throw new RuntimeException('WFP CSV does not exist or cannot be read.');
    }
    $file = fopen($csvPath, 'rb');
    if ($file === false) {
        throw new RuntimeException('Could not open the WFP CSV.');
    }

    $headers = fgetcsv($file, null, ',', '"', '');
    if (!is_array($headers)) {
        fclose($file);
        throw new RuntimeException('WFP CSV is empty.');
    }
    $headers = array_map(
        static fn ($header): string => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $header)),
        $headers
    );
    $requiredHeaders = ['date', 'admin2', 'market', 'commodity', 'unit', 'priceflag', 'pricetype', 'currency', 'price'];
    if (array_diff($requiredHeaders, $headers) !== []) {
        fclose($file);
        throw new RuntimeException('WFP CSV is missing required fields.');
    }

    $foods = [];
    foreach ($pdo->query('SELECT id, name, unit_label FROM foods WHERE is_active = 1') as $food) {
        $foods[(string) $food['name']] = [
            'id' => (int) $food['id'],
            'unit_label' => (string) $food['unit_label'],
        ];
    }
    $districts = [];
    foreach ($pdo->query('SELECT name FROM districts') as $district) {
        $districts[strtolower(trim((string) $district['name']))] = (string) $district['name'];
    }

    $today = new DateTimeImmutable('today');
    $cutoff = $today->modify('-365 days')->format('Y-m-d');
    $todayString = $today->format('Y-m-d');
    $counts = ['inserted' => 0, 'updated' => 0, 'skipped' => 0];
    $pricesByKey = [];
    $recordKey = static fn (array $row): string => implode("\0", [
        (string) $row['food_id'],
        (string) $row['market_name'],
        (string) $row['district'],
        (string) $row['recorded_on'],
    ]);
    $line = 1;

    try {
        while (($record = fgetcsv($file, null, ',', '"', '')) !== false) {
            $line++;
            if ($record === [null] || $record === []) {
                continue;
            }
            if (count($record) !== count($headers)) {
                throw new RuntimeException('CSV row ' . $line . ' has the wrong number of columns.');
            }
            $row = array_combine($headers, $record);
            if (!is_array($row)) {
                throw new RuntimeException('Could not parse WFP CSV row ' . $line . '.');
            }

            $commodity = trim((string) $row['commodity']);
            if (!isset(WFP_FOOD_MAPPINGS[$commodity])) {
                continue;
            }
            [$foodName, $expectedUnit, $appUnit] = WFP_FOOD_MAPPINGS[$commodity];
            $districtKey = strtolower(trim((string) $row['admin2']));
            $date = trim((string) $row['date']);
            $market = trim((string) $row['market']);
            $price = trim((string) $row['price']);
            $priceFlag = strtolower(trim((string) $row['priceflag']));
            if (strtoupper(trim((string) $row['unit'])) !== $expectedUnit
                || strtoupper(trim((string) $row['currency'])) !== 'RWF'
                || !in_array($priceFlag, ['actual', 'aggregate', 'actual,aggregate'], true)
                || strcasecmp(trim((string) $row['pricetype']), 'Retail') !== 0
                || !isset($districts[$districtKey])
                || $market === ''
                || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)
                || $date < $cutoff
                || $date > $todayString
                || !is_numeric($price)
                || !is_finite((float) $price)
                || (float) $price <= 0
                || !isset($foods[$foodName])
                || $foods[$foodName]['unit_label'] !== $appUnit
            ) {
                $counts['skipped']++;
                continue;
            }

            $priceRecord = [
                'food_id' => $foods[$foodName]['id'],
                'market_name' => $market,
                'district' => $districts[$districtKey],
                'recorded_on' => $date,
                'price' => (float) $price,
            ];
            $pricesByKey[$recordKey($priceRecord)] = $priceRecord;
        }
    } catch (Throwable $error) {
        fclose($file);
        throw $error;
    }
    fclose($file);

    $existingRows = $pdo->prepare(
        'SELECT id, food_id, price_rwf, market_name, district, recorded_on
         FROM prices
         WHERE source = :source AND source_reference = :source_reference'
    );
    $existingRows->execute([
        ':source' => 'other_public_dataset',
        ':source_reference' => WFP_RWA_REFERENCE,
    ]);
    $existingByKey = [];
    foreach ($existingRows->fetchAll() as $row) {
        $existingByKey[$recordKey($row)] = $row;
    }

    $updates = [];
    $inserts = [];
    foreach ($pricesByKey as $key => $priceRecord) {
        if (!isset($existingByKey[$key])) {
            $inserts[] = $priceRecord;
            continue;
        }
        $existing = $existingByKey[$key];
        if (round((float) $existing['price_rwf'], 2) !== round((float) $priceRecord['price'], 2)) {
            $updates[] = [
                'id' => (int) $existing['id'],
                'price' => (float) $priceRecord['price'],
            ];
        }
    }

    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare('UPDATE prices SET price_rwf = :price WHERE id = :id');
        foreach ($updates as $row) {
            $update->execute([':price' => $row['price'], ':id' => $row['id']]);
        }

        foreach (array_chunk($inserts, 100) as $batch) {
            $placeholders = implode(', ', array_fill(0, count($batch), '(?, ?, ?, ?, ?, ?, ?)'));
            $insert = $pdo->prepare(
                'INSERT INTO prices
                    (food_id, price_rwf, market_name, district, recorded_on, source, source_reference)
                 VALUES ' . $placeholders
            );
            $bindings = [];
            foreach ($batch as $row) {
                $bindings[] = $row['food_id'];
                $bindings[] = $row['price'];
                $bindings[] = $row['market_name'];
                $bindings[] = $row['district'];
                $bindings[] = $row['recorded_on'];
                $bindings[] = 'other_public_dataset';
                $bindings[] = WFP_RWA_REFERENCE;
            }
            $insert->execute($bindings);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
    $counts['inserted'] = count($inserts);
    $counts['updated'] = count($updates);

    return $counts;
}

try {
    if ($argc > 2) {
        throw new RuntimeException('Usage: php bin/import_wfp_rwanda_prices.php [path-to-csv].');
    }
    $downloadedPath = null;
    $csvPath = $argv[1] ?? null;
    if ($csvPath === null) {
        $downloadedPath = wfp_download_csv();
        $csvPath = $downloadedPath;
    }
    try {
        $counts = import_wfp_prices(get_db_connection(), $csvPath);
    } finally {
        if ($downloadedPath !== null && is_file($downloadedPath)) {
            unlink($downloadedPath);
        }
    }

    fwrite(
        STDOUT,
        'WFP Rwanda price import complete: ' . $counts['inserted'] . ' inserted, ' .
        $counts['updated'] . ' updated, ' . $counts['skipped'] . ' mapped rows skipped.' . PHP_EOL
    );
} catch (Throwable $error) {
    error_log('WFP Rwanda price import failed: ' . $error->getMessage());
    fwrite(STDERR, 'Import failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
