<?php
/**
 * Crop Advisor domain logic (Phase 2).
 *
 * Gathers the scoring inputs for each candidate crop in a district
 * — soil, climate, and soil stewardship come from agro_suitability; market and
 * nutrition are computed here from Phase 1's prices/nutrient_profiles
 * tables — then hands them to the compiled C++ scoring engine
 * (bin/crop_scorer) to weight and rank.
 */

declare(strict_types=1);

const CROP_SCORER_BINARY = __DIR__ . '/../bin/crop_scorer' . (PHP_OS_FAMILY === 'Windows' ? '.exe' : '');

function crop_scorer_build_command(): string
{
    return PHP_OS_FAMILY === 'Windows'
        ? 'g++ -std=c++17 -O2 -Wall -o bin/crop_scorer.exe cpp/crop_scorer.cpp'
        : 'g++ -std=c++17 -O2 -Wall -o bin/crop_scorer cpp/crop_scorer.cpp';
}

function fetch_open_meteo_json(string $url): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('Live weather requires the PHP cURL extension.');
    }

    $handle = curl_init($url);
    if ($handle === false) {
        throw new RuntimeException('Could not initialize the weather request.');
    }

    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_USERAGENT => 'UmurimaData Crop Advisor/1.0',
    ]);
    $body = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $curlError = curl_error($handle);
    curl_close($handle);

    if ($body === false) {
        error_log('Open-Meteo request failed: ' . $curlError);
        throw new RuntimeException('Live weather data is temporarily unavailable. Please try again shortly.');
    }
    if ($status < 200 || $status >= 300) {
        error_log('Open-Meteo returned HTTP ' . $status . ': ' . substr((string) $body, 0, 300));
        throw new RuntimeException('Live weather data is temporarily unavailable. Please try again shortly.');
    }

    try {
        $decoded = json_decode((string) $body, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        error_log('Open-Meteo returned invalid JSON: ' . $e->getMessage());
        throw new RuntimeException('Live weather data could not be read. Please try again shortly.');
    }
    if (!is_array($decoded)) {
        throw new RuntimeException('Live weather data had an unexpected format.');
    }

    return $decoded;
}

function fetch_district_weather_forecast(array $district): array
{
    $cacheDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'umurima-weather-cache';
    if (!is_dir($cacheDirectory) && !mkdir($cacheDirectory, 0700, true) && !is_dir($cacheDirectory)) {
        throw new RuntimeException('Weather forecast caching is unavailable on this server.');
    }

    $cacheKey = hash('sha256', strtolower((string) $district['name'] . '|' . (string) $district['province']));
    $cacheFile = $cacheDirectory . DIRECTORY_SEPARATOR . $cacheKey . '.json';
    if (is_file($cacheFile) && filemtime($cacheFile) !== false && time() - filemtime($cacheFile) < 10800) {
        $cachedJson = file_get_contents($cacheFile);
        if ($cachedJson !== false) {
            $cached = json_decode($cachedJson, true);
            if (is_array($cached)
                && isset($cached['seasonal_forecast'], $cached['default_forecast_season'], $cached['fetched_at'])
            ) {
                return $cached;
            }
        }
        error_log('Ignoring invalid cached weather data for district ' . $district['name']);
    }

    $provinceName = (string) $district['province'];
    if (strtolower($provinceName) === 'kigali city') {
        $provinceName = 'Kigali';
    } elseif (stripos($provinceName, 'province') === false) {
        $provinceName .= ' Province';
    }
    $locationSearchNames = [
        'Nyaruguru' => 'Kibeho',
        'Rusizi' => 'Kamembe',
    ];
    $searchNames = [(string) $district['name']];
    if (isset($locationSearchNames[$district['name']])) {
        $searchNames[] = $locationSearchNames[$district['name']];
    }
    $expectedDistrict = strtolower((string) $district['name'] . ' district');
    $expectedProvince = strtolower(str_replace(' province', '', $provinceName));
    $location = null;
    foreach ($searchNames as $searchName) {
        $geocodingUrl = 'https://geocoding-api.open-meteo.com/v1/search?' . http_build_query([
            'name' => $searchName . ', ' . $provinceName,
            'count' => 10,
            'language' => 'en',
            'format' => 'json',
            'countryCode' => 'RW',
        ]);
        $geocoding = fetch_open_meteo_json($geocodingUrl);
        foreach (($geocoding['results'] ?? []) as $result) {
            $adminDistrict = strtolower((string) ($result['admin2'] ?? ''));
            $adminProvince = strtolower(str_replace(' province', '', (string) ($result['admin1'] ?? '')));
            if (strtolower((string) ($result['country_code'] ?? '')) === 'rw'
                && $adminDistrict === $expectedDistrict
                && $adminProvince === $expectedProvince
                && isset($result['latitude'], $result['longitude'])
            ) {
                $location = $result;
                break 2;
            }
        }
    }
    if ($location === null) {
        error_log('Open-Meteo geocoding found no Rwanda district match for ' . $district['name']);
        throw new RuntimeException('Weather location could not be resolved for this district.');
    }

    $forecastUrl = 'https://seasonal-api.open-meteo.com/v1/seasonal?' . http_build_query([
        'latitude' => (float) $location['latitude'],
        'longitude' => (float) $location['longitude'],
        'models' => 'ecmwf_seas5_ensemble_mean',
        'daily' => 'temperature_2m_mean,precipitation_sum',
        'timezone' => 'Africa/Kigali',
        'temperature_unit' => 'celsius',
        'precipitation_unit' => 'mm',
    ]);
    $forecast = fetch_open_meteo_json($forecastUrl);
    $daily = $forecast['daily'] ?? null;
    if (!is_array($daily)
        || !isset($daily['time'], $daily['temperature_2m_mean'], $daily['precipitation_sum'])
        || !is_array($daily['time'])
        || count($daily['time']) < 25
    ) {
        throw new RuntimeException('The weather provider returned an incomplete seasonal forecast.');
    }

    $seasonValues = [];
    $days = count($daily['time']);
    for ($i = 0; $i < $days; $i++) {
        $date = $daily['time'][$i] ?? null;
        $temperature = $daily['temperature_2m_mean'][$i] ?? null;
        $rain = $daily['precipitation_sum'][$i] ?? null;
        if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)
            || !is_numeric($temperature) || !is_numeric($rain)
        ) {
            throw new RuntimeException('The weather provider returned incomplete daily seasonal values.');
        }
        $dateParts = explode('-', $date);
        $year = (int) $dateParts[0];
        $month = (int) $dateParts[1];
        if ($month >= 9 || $month <= 1) {
            $seasonYear = $month >= 9 ? $year : $year - 1;
            $seasonKey = 'A-' . $seasonYear;
            $seasonName = 'Season A ' . $seasonYear . '/' . ($seasonYear + 1);
            $seasonStart = sprintf('%04d-09-01', $seasonYear);
            $seasonEnd = sprintf('%04d-01-31', $seasonYear + 1);
        } elseif ($month <= 6) {
            $seasonYear = $year;
            $seasonKey = 'B-' . $seasonYear;
            $seasonName = 'Season B ' . $seasonYear;
            $seasonStart = sprintf('%04d-02-01', $seasonYear);
            $seasonEnd = sprintf('%04d-06-30', $seasonYear);
        } else {
            $seasonYear = $year;
            $seasonKey = 'C-' . $seasonYear;
            $seasonName = 'Season C ' . $seasonYear;
            $seasonStart = sprintf('%04d-07-01', $seasonYear);
            $seasonEnd = sprintf('%04d-08-31', $seasonYear);
        }
        if (!isset($seasonValues[$seasonKey])) {
            $seasonValues[$seasonKey] = [
                'name' => $seasonName,
                'season_start' => $seasonStart,
                'season_end' => $seasonEnd,
                'temperature_total' => 0.0,
                'precipitation_total' => 0.0,
                'days' => 0,
                'forecast_start' => $date,
                'forecast_end' => $date,
            ];
        }
        $seasonValues[$seasonKey]['temperature_total'] += (float) $temperature;
        $seasonValues[$seasonKey]['precipitation_total'] += (float) $rain;
        $seasonValues[$seasonKey]['days']++;
        $seasonValues[$seasonKey]['forecast_end'] = $date;
    }
    $seasonalForecast = [];
    foreach ($seasonValues as $seasonKey => $values) {
        $seasonStart = new DateTimeImmutable($values['season_start']);
        $seasonEnd = new DateTimeImmutable($values['season_end']);
        $expectedDays = (int) $seasonStart->diff($seasonEnd)->days + 1;
        $coverage = $values['days'] / $expectedDays;
        if ($coverage < 0.70) {
            continue;
        }
        $seasonalForecast[$seasonKey] = [
            'season' => $seasonKey,
            'label' => $values['name'],
            'season_start' => $values['season_start'],
            'season_end' => $values['season_end'],
            'coverage_percent' => $coverage * 100.0,
            'forecast_days' => $values['days'],
            'forecast_start' => $values['forecast_start'],
            'forecast_end' => $values['forecast_end'],
            'temperature_mean_c' => $values['temperature_total'] / $values['days'],
            'precipitation_total_mm' => $values['precipitation_total'],
            'rainfall_weekly_equivalent_mm' => $values['precipitation_total'] * 7.0 / $values['days'],
        ];
    }
    if ($seasonalForecast === []) {
        throw new RuntimeException('The weather provider returned no season with enough forecast coverage.');
    }
    uasort($seasonalForecast, static function (array $first, array $second): int {
        return strcmp($first['forecast_start'], $second['forecast_start']);
    });
    $weather = [
        'provider' => 'Open-Meteo',
        'model' => 'ECMWF SEAS5 ensemble mean',
        'location' => (string) ($location['name'] ?? $district['name']),
        'district' => (string) $district['name'],
        'latitude' => (float) $location['latitude'],
        'longitude' => (float) $location['longitude'],
        'timezone' => (string) ($forecast['timezone'] ?? 'Africa/Kigali'),
        'seasonal_forecast' => $seasonalForecast,
        'default_forecast_season' => (string) array_key_first($seasonalForecast),
        'fetched_at' => gmdate('c'),
    ];

    if (file_put_contents($cacheFile, json_encode($weather, JSON_THROW_ON_ERROR), LOCK_EX) === false) {
        error_log('Could not cache Open-Meteo forecast for district ' . $district['name']);
    }

    return $weather;
}

function crop_weather_score(string $cropName, float $averageTemperatureC, float $weeklyRainMm): float
{
    $requirements = [
        'potato' => [15, 22, 15, 70], 'rice' => [22, 30, 35, 110],
        'maize' => [18, 30, 20, 85], 'cassava' => [20, 30, 15, 75],
        'sorghum' => [20, 32, 10, 60], 'common beans' => [16, 26, 20, 70],
        'soybean' => [20, 30, 25, 85], 'sweet potato' => [20, 30, 20, 80],
        'avocado' => [18, 28, 20, 80], 'cabbage' => [15, 24, 20, 75],
        'carrot' => [15, 24, 15, 65], 'amaranth (dodo)' => [20, 32, 15, 75],
        'plantain (matoke)' => [22, 30, 35, 120], 'groundnut' => [20, 30, 15, 70],
        'onion' => [15, 25, 10, 55], 'banana' => [22, 30, 35, 120],
        'tomato' => [18, 28, 15, 75],
    ];
    $profile = $requirements[strtolower(trim($cropName))] ?? [18, 30, 15, 85];

    $rangeScore = static function (float $value, float $minimum, float $maximum, float $tolerance): float {
        if ($value >= $minimum && $value <= $maximum) {
            return 100.0;
        }
        $distance = $value < $minimum ? $minimum - $value : $value - $maximum;
        return max(0.0, 100.0 * (1.0 - $distance / $tolerance));
    };

    $temperatureScore = $rangeScore($averageTemperatureC, $profile[0], $profile[1], 8.0);
    $rainfallScore = $rangeScore($weeklyRainMm, $profile[2], $profile[3], 50.0);
    return ($temperatureScore * 0.60) + ($rainfallScore * 0.40);
}

function incorporate_forecast_weather(array &$candidates, array $weather, string $forecastSeason): void
{
    $seasonForecast = $weather['seasonal_forecast'][$forecastSeason] ?? null;
    if (!is_array($seasonForecast)) {
        throw new InvalidArgumentException('The selected season is outside the available seasonal forecast.');
    }
    foreach ($candidates as &$candidate) {
        $baseline = (float) $candidate['climate_score'];
        $weatherScore = crop_weather_score(
            (string) $candidate['crop_name'],
            (float) $seasonForecast['temperature_mean_c'],
            (float) $seasonForecast['rainfall_weekly_equivalent_mm']
        );
        $candidate['climate_baseline_score'] = $baseline;
        $candidate['weather_score'] = $weatherScore;
        $candidate['climate_score'] = ($baseline * 0.70) + ($weatherScore * 0.30);
    }
    unset($candidate);
}

/** All districts, for the location selector. */
function get_districts_list(PDO $pdo): array
{
    return $pdo->query('SELECT id, name, province FROM districts ORDER BY province, name')->fetchAll();
}

/**
 * Compute a 0-100 market-price-momentum score for a food: how the
 * latest recorded price compares to the average of prior recorded
 * prices. 50 = flat/no history, >50 = price trending up (good time
 * to plant/sell), <50 = trending down.
 */
function compute_market_score(PDO $pdo, int $foodId): float
{
    $stmt = $pdo->prepare('SELECT price_rwf, recorded_on FROM prices WHERE food_id = :id ORDER BY recorded_on ASC');
    $stmt->execute([':id' => $foodId]);
    $rows = $stmt->fetchAll();

    if (count($rows) < 2) {
        return 50.0; // not enough history to compute a trend
    }

    $latest = (float) end($rows)['price_rwf'];
    $priorRows = array_slice($rows, 0, -1);
    $priorAvg = array_sum(array_map(function ($r) { return (float) $r['price_rwf']; }, $priorRows)) / count($priorRows);

    if ($priorAvg <= 0.0) {
        return 50.0;
    }

    $momentumPct = (($latest - $priorAvg) / $priorAvg) * 100.0;
    // Scale factor of 2: a ±25% swing maps to a ±50-point swing
    // around the neutral midpoint of 50.
    $score = 50.0 + ($momentumPct * 2.0);

    return max(0.0, min(100.0, $score));
}

/**
 * Gather all district crop candidates with soil/climate scores, market
 * scores, and harvest-scaled nutrient totals. Nutrients are normalized
 * across candidates and balanced across groups.
 */
function crop_nutrient_definitions(): array
{
    return [
        'calories_kcal' => ['label' => 'Energy', 'group' => 'Energy & macros', 'display_unit' => 'million kcal', 'display_divisor' => 1000000.0],
        'protein_g' => ['label' => 'Protein', 'group' => 'Energy & macros', 'display_unit' => 'kg', 'display_divisor' => 1000.0],
        'fat_g' => ['label' => 'Fat', 'group' => 'Energy & macros', 'display_unit' => 'kg', 'display_divisor' => 1000.0],
        'carbohydrates_g' => ['label' => 'Carbohydrates', 'group' => 'Energy & macros', 'display_unit' => 'kg', 'display_divisor' => 1000.0],
        'fiber_g' => ['label' => 'Fiber', 'group' => 'Energy & macros', 'display_unit' => 'kg', 'display_divisor' => 1000.0],
        'iron_mg' => ['label' => 'Iron', 'group' => 'Minerals', 'display_unit' => 'kg', 'display_divisor' => 1000000.0],
        'zinc_mg' => ['label' => 'Zinc', 'group' => 'Minerals', 'display_unit' => 'kg', 'display_divisor' => 1000000.0],
        'calcium_mg' => ['label' => 'Calcium', 'group' => 'Minerals', 'display_unit' => 'kg', 'display_divisor' => 1000000.0],
        'potassium_mg' => ['label' => 'Potassium', 'group' => 'Minerals', 'display_unit' => 'kg', 'display_divisor' => 1000000.0],
        'vitamin_a_ug' => ['label' => 'Vitamin A', 'group' => 'Vitamins', 'display_unit' => 'g', 'display_divisor' => 1000000.0],
        'vitamin_c_mg' => ['label' => 'Vitamin C', 'group' => 'Vitamins', 'display_unit' => 'kg', 'display_divisor' => 1000000.0],
        'folate_ug' => ['label' => 'Folate', 'group' => 'Vitamins', 'display_unit' => 'g', 'display_divisor' => 1000000.0],
        'vitamin_b12_ug' => ['label' => 'Vitamin B12', 'group' => 'Vitamins', 'display_unit' => 'g', 'display_divisor' => 1000000.0],
    ];
}

function get_crop_candidates(PDO $pdo, int $districtId): array
{
    $nutrientDefinitions = crop_nutrient_definitions();
    $nutrientColumns = implode(', ', array_map(
        static function (string $column): string {
            return 'np.' . $column;
        },
        array_keys($nutrientDefinitions)
    ));
    $stmt = $pdo->prepare('
        SELECT
            c.id AS crop_id,
            c.name AS crop_name,
            c.food_id,
            c.typical_yield_kg_per_hectare,
            a.soil_score,
            a.climate_score,
            a.erosion_control_score,
            a.nutrient_balance_score,
            a.soil_structure_score,
            a.erosion_risk,
            a.fertilizer_note,
            a.source,
            a.source_reference,
            c.rotation_guidance,
            c.soil_stewardship_guidance,
            ' . $nutrientColumns . ',
            f.name AS food_name,
            f.unit_label
        FROM crops c
        INNER JOIN agro_suitability a ON a.crop_id = c.id AND a.district_id = :district_id
        INNER JOIN nutrient_profiles np ON np.food_id = c.food_id
        INNER JOIN foods f ON f.id = c.food_id
        ORDER BY c.name
    ');
    $stmt->execute([':district_id' => $districtId]);
    $rows = $stmt->fetchAll();

    if (empty($rows)) {
        return [];
    }

    $nutrientTotals = [];
    $nutrientTotalsByCrop = [];
    foreach ($rows as &$row) {
        $row['market_score'] = compute_market_score($pdo, (int) $row['food_id']);
        $harvestKg = (float) $row['typical_yield_kg_per_hectare'];
        $row['harvest_tonnes_per_hectare'] = $harvestKg / 1000.0;
        $row['nutrition_per_hectare'] = [];
        foreach ($nutrientDefinitions as $column => $definition) {
            $total = (float) $row[$column] * 10.0 * $harvestKg;
            $nutrientTotals[$column][] = $total;
            $nutrientTotalsByCrop[$row['crop_id']][$column] = $total;
            $row['nutrition_per_hectare'][$column] = [
                'label' => $definition['label'],
                'group' => $definition['group'],
                'value' => $total / $definition['display_divisor'],
                'unit' => $definition['display_unit'],
            ];
        }
    }
    unset($row);

    $groupScoresByCrop = [];
    foreach ($nutrientDefinitions as $column => $definition) {
        $minimum = min($nutrientTotals[$column]);
        $maximum = max($nutrientTotals[$column]);
        if ($maximum <= $minimum) {
            continue;
        }
        foreach ($rows as $row) {
            $normalized = (($nutrientTotalsByCrop[$row['crop_id']][$column] - $minimum) / ($maximum - $minimum)) * 100.0;
            $groupScoresByCrop[$row['crop_id']][$definition['group']][] = $normalized;
        }
    }

    foreach ($rows as &$row) {
        $row['nutrition_group_scores'] = [];
        foreach (['Energy & macros', 'Minerals', 'Vitamins'] as $group) {
            $values = $groupScoresByCrop[$row['crop_id']][$group] ?? [];
            if ($values !== []) {
                $row['nutrition_group_scores'][$group] = array_sum($values) / count($values);
            }
        }
        $groups = array_values($row['nutrition_group_scores']);
        $row['nutrition_score'] = $groups !== [] ? array_sum($groups) / count($groups) : 50.0;
    }
    unset($row);

    return $rows;
}

/**
 * Run the C++ scoring engine on a set of candidates and return the
 * ranked result. Throws RuntimeException on any failure (missing
 * binary, non-zero exit, malformed output) — callers should catch
 * this and show a friendly message rather than a raw stack trace.
 */
function run_crop_scorer(array $candidates, array $weights = []): array
{
    if (!is_executable(CROP_SCORER_BINARY)) {
        throw new RuntimeException(
            'Crop scoring engine is not built yet. Run: ' . crop_scorer_build_command()
        );
    }

    $payload = [
        'weights' => $weights,
        'candidates' => array_map(function ($c) {
            return [
                'crop_id' => (int) $c['crop_id'],
                'crop_name' => $c['crop_name'],
                'soil_score' => (float) $c['soil_score'],
                'climate_score' => (float) $c['climate_score'],
                'market_score' => (float) $c['market_score'],
                'nutrition_score' => (float) $c['nutrition_score'],
                'erosion_control_score' => (float) $c['erosion_control_score'],
                'nutrient_balance_score' => (float) $c['nutrient_balance_score'],
                'soil_structure_score' => (float) $c['soil_structure_score'],
            ];
        }, $candidates),
    ];

    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    // Passed as an array (not a shell string) so the binary runs
    // directly with no shell interpretation of its arguments.
    $process = proc_open([CROP_SCORER_BINARY], $descriptorSpec, $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start the crop scoring engine.');
    }

    fwrite($pipes[0], json_encode($payload));
    fclose($pipes[0]);

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    $decoded = json_decode((string) $stdout, true);

    if ($exitCode !== 0 || !is_array($decoded)) {
        error_log("crop_scorer failed (exit {$exitCode}): stdout=" . $stdout . ' stderr=' . $stderr);
        throw new RuntimeException('The crop scoring engine could not process this request.');
    }

    if (isset($decoded['error'])) {
        error_log('crop_scorer reported an error: ' . $decoded['error']);
        throw new RuntimeException('The crop scoring engine reported an error.');
    }

    $weightsUsed = $decoded['weights_used'] ?? [];
    $firstResult = $decoded['results'][0] ?? [];
    if (!is_array($weightsUsed)
        || !array_key_exists('stewardship', $weightsUsed)
        || !is_array($firstResult)
        || !array_key_exists('soil_stewardship_score', $firstResult)
    ) {
        throw new RuntimeException(
            'Crop scoring engine is out of date. Rebuild it with: ' . crop_scorer_build_command()
        );
    }

    return $decoded['results'] ?? [];
}

/** Attach the erosion_risk/fertilizer_note context back onto scored results, for display. */
function merge_candidate_context(array $scoredResults, array $rawCandidates): array
{
    $byId = [];
    foreach ($rawCandidates as $c) {
        $byId[(int) $c['crop_id']] = $c;
    }

    foreach ($scoredResults as &$result) {
        $context = $byId[(int) $result['crop_id']] ?? null;
        if ($context !== null) {
            $result['erosion_risk'] = $context['erosion_risk'];
            $result['fertilizer_note'] = $context['fertilizer_note'];
            $result['soil_data_source'] = $context['source'];
            $result['source_reference'] = $context['source_reference'];
            $result['climate_baseline_score'] = $context['climate_baseline_score'] ?? $context['climate_score'];
            $result['weather_score'] = $context['weather_score'] ?? null;
            $result['rotation_guidance'] = $context['rotation_guidance'];
            $result['soil_stewardship_guidance'] = $context['soil_stewardship_guidance'];
            $result['food_name'] = $context['food_name'];
            $result['unit_label'] = $context['unit_label'];
            $result['typical_yield_kg_per_hectare'] = $context['typical_yield_kg_per_hectare'];
            $result['harvest_tonnes_per_hectare'] = $context['harvest_tonnes_per_hectare'];
            $result['nutrition_group_scores'] = $context['nutrition_group_scores'];
            $result['nutrition_per_hectare'] = $context['nutrition_per_hectare'];
        }
    }
    unset($result);

    return $scoredResults;
}
