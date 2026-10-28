<?php
/**
 * Crop Advisor domain logic (Phase 2).
 *
 * Gathers cited scoring inputs available for each candidate crop and
 * renormalizes the composite weights when a complete dimension is unavailable.
 * The PHP layer then applies observed-yield and food-production-priority
 * adjustments after the compiled C++ scoring engine calculates supporting scores.
 */

declare(strict_types=1);

const CROP_SCORER_BINARY = __DIR__ . '/../bin/crop_scorer' . (PHP_OS_FAMILY === 'Windows' ? '.exe' : '');
const OBSERVED_YIELD_SCORE_WEIGHT = 0.40;
const PRODUCTION_PRIORITY_SCORE_WEIGHT = 0.20;
const NISR_PUBLISHER = 'National Institute of Statistics of Rwanda (NISR)';

function crop_production_priority_score(string $foodCategory): float
{
    return match ($foodCategory) {
        'Grains', 'Tubers', 'Legumes' => 100.0,
        'Fruits & Starch' => 75.0,
        'Vegetables' => 20.0,
        default => 50.0,
    };
}

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
                && ($cached['coverage_version'] ?? null) === 2
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
        || !is_array($daily['temperature_2m_mean'])
        || !is_array($daily['precipitation_sum'])
        || count($daily['time']) < 25
    ) {
        throw new RuntimeException('The weather provider returned an incomplete seasonal forecast.');
    }

    $seasonValues = [];
    $days = count($daily['time']);
    $incompleteDays = 0;
    $today = new DateTimeImmutable('today', new DateTimeZone('Africa/Kigali'));
    $todayIso = $today->format('Y-m-d');
    for ($i = 0; $i < $days; $i++) {
        $date = $daily['time'][$i] ?? null;
        $temperature = $daily['temperature_2m_mean'][$i] ?? null;
        $rain = $daily['precipitation_sum'][$i] ?? null;
        if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)) {
            throw new RuntimeException('The weather provider returned incomplete daily seasonal values.');
        }
        if ($date < $todayIso) {
            continue;
        }
        if (!is_numeric($temperature) || !is_numeric($rain)) {
            $incompleteDays++;
            continue;
        }
        $dateParts = explode('-', $date);
        $year = (int) $dateParts[0];
        $month = (int) $dateParts[1];
        if ($month >= 9 || $month <= 2) {
            $seasonYear = $month >= 9 ? $year : $year - 1;
            $seasonKey = 'A-' . $seasonYear;
            $seasonName = 'Season A ' . $seasonYear . '/' . ($seasonYear + 1);
            $seasonStart = sprintf('%04d-09-01', $seasonYear);
            $seasonEnd = sprintf('%04d-02-28', $seasonYear + 1);
            if (checkdate(2, 29, $seasonYear + 1)) {
                $seasonEnd = sprintf('%04d-02-29', $seasonYear + 1);
            }
        } elseif ($month <= 6) {
            $seasonYear = $year;
            $seasonKey = 'B-' . $seasonYear;
            $seasonName = 'Season B ' . $seasonYear;
            $seasonStart = sprintf('%04d-03-01', $seasonYear);
            $seasonEnd = sprintf('%04d-06-30', $seasonYear);
        } else {
            $seasonYear = $year;
            $seasonKey = 'C-' . $seasonYear;
            $seasonName = 'Season C ' . $seasonYear;
            $seasonStart = sprintf('%04d-07-01', $seasonYear);
            $seasonEnd = sprintf('%04d-09-30', $seasonYear);
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
    if ($incompleteDays > 0) {
        error_log(
            'Open-Meteo returned incomplete daily seasonal values for district ' .
            $district['name'] . '; skipped ' . $incompleteDays . ' day(s).'
        );
    }
    $seasonalForecast = [];
    foreach ($seasonValues as $seasonKey => $values) {
        $seasonStart = new DateTimeImmutable($values['season_start']);
        $seasonEnd = new DateTimeImmutable($values['season_end']);
        if ($seasonEnd < $today) {
            continue;
        }
        $coverageStart = $seasonStart > $today ? $seasonStart : $today;
        $expectedDays = (int) $coverageStart->diff($seasonEnd)->days + 1;
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
        'coverage_version' => 2,
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
    $profile = $requirements[strtolower(trim($cropName))] ?? null;
    if ($profile === null) {
        throw new RuntimeException('No weather-fit profile is configured for crop: ' . $cropName . '.');
    }

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
        $baseline = is_numeric($candidate['climate_score'])
            ? (float) $candidate['climate_score']
            : null;
        $weatherScore = crop_weather_score(
            (string) $candidate['crop_name'],
            (float) $seasonForecast['temperature_mean_c'],
            (float) $seasonForecast['rainfall_weekly_equivalent_mm']
        );
        $candidate['climate_baseline_score'] = $baseline;
        $candidate['weather_score'] = $weatherScore;
        $candidate['climate_score'] = $baseline === null
            ? $weatherScore
            : ($baseline * 0.70) + ($weatherScore * 0.30);
    }
    unset($candidate);
}

function available_crop_scoring_weights(array $candidates): array
{
    $allHave = static function (array $columns) use ($candidates): bool {
        foreach ($candidates as $candidate) {
            foreach ($columns as $column) {
                if (!isset($candidate[$column]) || !is_numeric($candidate[$column])) {
                    return false;
                }
            }
        }
        return $candidates !== [];
    };

    return [
        'soil' => $allHave(['soil_score']) ? 0.25 : 0.0,
        'climate' => $allHave(['climate_score']) ? 0.20 : 0.0,
        'market' => 0.20,
        'nutrition' => 0.15,
        'stewardship' => $allHave([
            'erosion_control_score',
            'nutrient_balance_score',
            'soil_structure_score',
        ]) ? 0.20 : 0.0,
    ];
}

function current_rwanda_growing_season(): string
{
    $today = new DateTimeImmutable('now', new DateTimeZone('Africa/Kigali'));
    $month = (int) $today->format('n');
    $year = (int) $today->format('Y');
    if ($month >= 9) {
        return 'A-' . ($year + 1);
    }
    if ($month <= 2) {
        return 'A-' . $year;
    }
    if ($month <= 6) {
        return 'B-' . $year;
    }
    return 'C-' . $year;
}

/** All districts, for the location selector. */
function get_districts_list(PDO $pdo): array
{
    return $pdo->query('SELECT id, name, province FROM districts ORDER BY province, name')->fetchAll();
}

/**
 * Compute a 0-100 market-price-momentum score for a food: how the
 * latest recorded price compares to the average of prior recorded
 * prices from one district within the last year. It aggregates market
 * observations by date so several markets on one day cannot masquerade
 * as a temporal trend. 50 means insufficient history or no net change.
 */
function compute_market_score(PDO $pdo, int $foodId, string $districtName): array
{
    $stmt = $pdo->prepare('
        SELECT price_rwf, recorded_on, source_reference
        FROM prices
        WHERE food_id = :food_id
          AND district = :district
          AND source <> :sample_source
          AND recorded_on >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
          AND recorded_on <= CURDATE()
        ORDER BY recorded_on ASC, id ASC
    ');
    $stmt->execute([
        ':food_id' => $foodId,
        ':district' => $districtName,
        ':sample_source' => 'sample_placeholder',
    ]);
    $rows = $stmt->fetchAll();
    $sourceReferences = array_values(array_unique(array_filter(
        array_map(static fn (array $row): string => trim((string) ($row['source_reference'] ?? '')), $rows),
        static fn (string $reference): bool => $reference !== ''
    )));

    $pricesByDate = [];
    foreach ($rows as $row) {
        $date = (string) $row['recorded_on'];
        $pricesByDate[$date][] = (float) $row['price_rwf'];
    }
    $dailyAverages = [];
    foreach ($pricesByDate as $date => $prices) {
        $dailyAverages[$date] = array_sum($prices) / count($prices);
    }
    $dates = array_keys($dailyAverages);

    if (count($dates) < 2) {
        return [
            'score' => 50.0,
            'observation_count' => count($rows),
            'observation_dates' => count($dates),
            'first_date' => $dates[0] ?? null,
            'latest_date' => $dates[0] ?? null,
            'source_references' => $sourceReferences,
        ];
    }

    $latestDate = end($dates);
    $latest = $dailyAverages[$latestDate];
    $priorAverages = array_slice(array_values($dailyAverages), 0, -1);
    $priorAvg = array_sum($priorAverages) / count($priorAverages);

    if ($priorAvg <= 0.0) {
        return [
            'score' => 50.0,
            'observation_count' => count($rows),
            'observation_dates' => count($dates),
            'first_date' => $dates[0],
            'latest_date' => $latestDate,
            'source_references' => $sourceReferences,
        ];
    }

    $momentumPct = (($latest - $priorAvg) / $priorAvg) * 100.0;
    // Scale factor of 2: a ±25% swing maps to a ±50-point swing
    // around the neutral midpoint of 50.
    $score = 50.0 + ($momentumPct * 2.0);

    return [
        'score' => max(0.0, min(100.0, $score)),
        'observation_count' => count($rows),
        'observation_dates' => count($dates),
        'first_date' => $dates[0],
        'latest_date' => $latestDate,
        'source_references' => $sourceReferences,
    ];
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

function get_crop_candidates(PDO $pdo, int $districtId, string $season): array
{
    if (!in_array($season, ['A', 'B', 'C'], true)) {
        throw new InvalidArgumentException('A valid Rwanda growing season is required.');
    }

    $districtStatement = $pdo->prepare('SELECT name FROM districts WHERE id = :id');
    $districtStatement->execute([':id' => $districtId]);
    $districtName = $districtStatement->fetchColumn();
    if (!is_string($districtName)) {
        return [];
    }

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
            c.harvest_days_min,
            c.harvest_days_max,
            c.harvest_duration_note,
            c.notes AS crop_notes,
            a.soil_score,
            a.climate_score,
            a.erosion_control_score,
            a.nutrient_balance_score,
            a.soil_structure_score,
            a.erosion_risk,
            a.fertilizer_note,
            a.source,
            a.source_reference,
            a.updated_at AS suitability_updated_at,
            c.rotation_guidance,
            c.soil_stewardship_guidance,
            ' . $nutrientColumns . ',
            np.source_note AS nutrition_source_note,
            f.name AS food_name,
            f.category AS food_category,
            f.unit_label
        FROM crops c
        LEFT JOIN agro_suitability a
          ON a.crop_id = c.id AND a.district_id = :district_id
         AND a.source <> :illustrative_source
         AND a.source_reference IS NOT NULL AND LENGTH(TRIM(a.source_reference)) > 0
         AND a.soil_score IS NOT NULL AND a.climate_score IS NOT NULL
         AND a.erosion_control_score IS NOT NULL
         AND a.nutrient_balance_score IS NOT NULL
         AND a.soil_structure_score IS NOT NULL
        INNER JOIN nutrient_profiles np ON np.food_id = c.food_id
        INNER JOIN foods f ON f.id = c.food_id
        WHERE (c.notes IS NULL OR c.notes NOT LIKE :illustrative_yield)
          AND np.source_note IS NOT NULL AND LENGTH(TRIM(np.source_note)) > 0
          AND np.source_note NOT LIKE :illustrative_nutrients
        ORDER BY c.name
    ');
    $stmt->execute([
        ':district_id' => $districtId,
        ':illustrative_source' => 'illustrative_estimate',
        ':illustrative_yield' => 'Illustrative planning yield%',
        ':illustrative_nutrients' => 'Illustrative seed estimate%',
    ]);
    $rows = $stmt->fetchAll();

    if (empty($rows)) {
        return [];
    }

    $statisticsQuery = $pdo->prepare('
        SELECT
            s.crop_id,
            s.survey_year,
            s.cultivated_area_ha,
            s.harvested_area_ha,
            s.production_tonnes,
            s.published_yield_kg_per_hectare,
            source.id AS data_source_id,
            source.dataset_title,
            source.publisher,
            source.reference_url,
            source.release_year,
            source.imported_at,
            source.is_active_for_scoring
        FROM district_crop_statistics s
        INNER JOIN agricultural_data_sources source ON source.id = s.data_source_id
        WHERE s.district_id = :district_id AND s.season = :season
          AND (source.is_active_for_scoring = 1 OR source.publisher = :nisr_publisher)
    ');
    $statisticsQuery->execute([
        ':district_id' => $districtId,
        ':season' => $season,
        ':nisr_publisher' => NISR_PUBLISHER,
    ]);
    $yieldRecordsByCropAndSource = [];
    foreach ($statisticsQuery->fetchAll() as $statistic) {
        $yieldKgPerHectare = $statistic['published_yield_kg_per_hectare'] !== null
            ? (float) $statistic['published_yield_kg_per_hectare']
            : null;
        if ($yieldKgPerHectare === null && $statistic['production_tonnes'] !== null) {
            if ($statistic['harvested_area_ha'] !== null && (float) $statistic['harvested_area_ha'] > 0) {
                $yieldKgPerHectare = ((float) $statistic['production_tonnes']
                    / (float) $statistic['harvested_area_ha']) * 1000.0;
            } elseif ($statistic['cultivated_area_ha'] !== null && (float) $statistic['cultivated_area_ha'] > 0) {
                $yieldKgPerHectare = ((float) $statistic['production_tonnes']
                    / (float) $statistic['cultivated_area_ha']) * 1000.0;
            }
        }
        if ($yieldKgPerHectare === null || $yieldKgPerHectare < 0) {
            continue;
        }
        $isNisrEvidence = $statistic['publisher'] === NISR_PUBLISHER;
        if (!$isNisrEvidence && !(bool) $statistic['is_active_for_scoring']) {
            continue;
        }
        $weight = $statistic['harvested_area_ha'] !== null
            ? (float) $statistic['harvested_area_ha']
            : (float) $statistic['cultivated_area_ha'];
        if ($weight <= 0) {
            $weight = 1.0;
        }
        $cropId = (int) $statistic['crop_id'];
        $sourceId = (int) $statistic['data_source_id'];
        if (!isset($yieldRecordsByCropAndSource[$cropId][$sourceId])) {
            $yieldRecordsByCropAndSource[$cropId][$sourceId] = [
                'weighted_yield_total' => 0.0,
                'weight_total' => 0.0,
                'years' => [],
                'dataset_title' => (string) $statistic['dataset_title'],
                'publisher' => (string) $statistic['publisher'],
                'reference_url' => (string) $statistic['reference_url'],
                'release_year' => (int) $statistic['release_year'],
                'imported_at' => (string) $statistic['imported_at'],
                'data_source_id' => $sourceId,
            ];
        }
        $yieldRecordsByCropAndSource[$cropId][$sourceId]['weighted_yield_total'] += $yieldKgPerHectare * $weight;
        $yieldRecordsByCropAndSource[$cropId][$sourceId]['weight_total'] += $weight;
        $yieldRecordsByCropAndSource[$cropId][$sourceId]['years'][] = (int) $statistic['survey_year'];
    }
    $yieldStatistics = [];
    foreach ($yieldRecordsByCropAndSource as $cropId => $sourceRecords) {
        uasort($sourceRecords, static function (array $left, array $right): int {
            $leftIsNisr = $left['publisher'] === NISR_PUBLISHER;
            $rightIsNisr = $right['publisher'] === NISR_PUBLISHER;
            if ($leftIsNisr !== $rightIsNisr) {
                return $leftIsNisr ? -1 : 1;
            }
            return [$right['release_year'], $right['imported_at'], $right['data_source_id']]
                <=> [$left['release_year'], $left['imported_at'], $left['data_source_id']];
        });
        $selectedSource = reset($sourceRecords);
        if ($selectedSource === false) {
            continue;
        }
        $years = array_values(array_unique($selectedSource['years']));
        sort($years);
        $yieldStatistics[$cropId] = [
            'yield_kg_per_hectare' => $selectedSource['weighted_yield_total'] / $selectedSource['weight_total'],
            'is_nisr_evidence' => $selectedSource['publisher'] === NISR_PUBLISHER,
            'observation_years' => count($years),
            'first_survey_year' => $years[0],
            'last_survey_year' => $years[count($years) - 1],
            'dataset_title' => $selectedSource['dataset_title'],
            'publisher' => $selectedSource['publisher'],
            'source_url' => $selectedSource['reference_url'],
        ];
    }
    $observedYieldValues = array_column($yieldStatistics, 'yield_kg_per_hectare');
    $observedYieldMinimum = $observedYieldValues === [] ? null : min($observedYieldValues);
    $observedYieldMaximum = $observedYieldValues === [] ? null : max($observedYieldValues);

    $rows = array_values(array_filter(
        $rows,
        static function (array $row) use ($yieldStatistics): bool {
            return $row['typical_yield_kg_per_hectare'] !== null
                || isset($yieldStatistics[(int) $row['crop_id']]);
        }
    ));
    if ($rows === []) {
        return [];
    }

    $nutrientTotals = [];
    $nutrientTotalsByCrop = [];
    foreach ($rows as &$row) {
        $row['production_priority_score'] = crop_production_priority_score((string) $row['food_category']);
        $marketEvidence = compute_market_score($pdo, (int) $row['food_id'], $districtName);
        $row['market_score'] = $marketEvidence['score'];
        $row['market_observation_count'] = $marketEvidence['observation_count'];
        $row['market_observation_dates'] = $marketEvidence['observation_dates'];
        $row['market_first_date'] = $marketEvidence['first_date'];
        $row['market_latest_date'] = $marketEvidence['latest_date'];
        $row['market_source_references'] = $marketEvidence['source_references'];
        $yieldEvidence = $yieldStatistics[(int) $row['crop_id']] ?? null;
        if ($yieldEvidence !== null) {
            $row['typical_yield_kg_per_hectare'] = $yieldEvidence['yield_kg_per_hectare'];
            $row['yield_data_source'] = $yieldEvidence['dataset_title'];
            $row['yield_source_publisher'] = $yieldEvidence['publisher'];
            $row['yield_source_is_nisr'] = $yieldEvidence['is_nisr_evidence'];
            $row['yield_source_reference'] = $yieldEvidence['source_url'];
            $row['yield_observation_years'] = $yieldEvidence['observation_years'];
            $row['yield_first_survey_year'] = $yieldEvidence['first_survey_year'];
            $row['yield_last_survey_year'] = $yieldEvidence['last_survey_year'];
        } else {
            $row['yield_data_source'] = null;
            $row['yield_source_publisher'] = null;
            $row['yield_source_is_nisr'] = false;
            $row['yield_source_reference'] = null;
            $row['yield_observation_years'] = 0;
            $row['yield_first_survey_year'] = null;
            $row['yield_last_survey_year'] = null;
        }
        $row['observed_yield_score'] = null;
        if ($yieldEvidence !== null) {
            $row['observed_yield_score'] = $observedYieldMinimum !== null
                && $observedYieldMaximum !== null
                && $observedYieldMaximum > $observedYieldMinimum
                ? (($yieldEvidence['yield_kg_per_hectare'] - $observedYieldMinimum)
                    / ($observedYieldMaximum - $observedYieldMinimum)) * 100.0
                : 50.0;
        }
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

    $results = $decoded['results'] ?? null;
    if (!is_array($results) || count($results) !== count($candidates)) {
        throw new RuntimeException(
            'Crop scoring engine is out of date. Rebuild it with: ' . crop_scorer_build_command()
        );
    }

    $candidatesById = [];
    foreach ($candidates as $candidate) {
        $candidatesById[(int) $candidate['crop_id']] = $candidate;
    }
    foreach ($results as &$result) {
        $candidate = $candidatesById[(int) ($result['crop_id'] ?? 0)] ?? null;
        if ($candidate === null || !isset($result['composite_score']) || !is_numeric($result['composite_score'])) {
            throw new RuntimeException('The crop scoring engine returned an invalid candidate result.');
        }
        $result['scoring_weights'] = $weightsUsed;
        $observedYieldScore = $candidate['observed_yield_score'] ?? null;
        $hasObservedYieldEvidence = is_numeric($observedYieldScore);
        $result['observed_yield_score'] = $hasObservedYieldEvidence ? (float) $observedYieldScore : null;
        $result['has_observed_yield_evidence'] = $hasObservedYieldEvidence;
        if ($hasObservedYieldEvidence) {
            $result['composite_score'] = ((float) $result['composite_score'] * (1.0 - OBSERVED_YIELD_SCORE_WEIGHT))
                + ((float) $observedYieldScore * OBSERVED_YIELD_SCORE_WEIGHT);
        }
        $productionPriorityScore = (float) $candidate['production_priority_score'];
        $result['production_priority_score'] = $productionPriorityScore;
        $result['composite_score'] = ((float) $result['composite_score'] * (1.0 - PRODUCTION_PRIORITY_SCORE_WEIGHT))
            + ($productionPriorityScore * PRODUCTION_PRIORITY_SCORE_WEIGHT);
    }
    unset($result);

    usort($results, static fn (array $left, array $right): int =>
        (float) $right['composite_score'] <=> (float) $left['composite_score']
    );

    return $results;
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
            $result['suitability_updated_at'] = $context['suitability_updated_at'];
            $result['crop_notes'] = $context['crop_notes'];
            $result['harvest_days_min'] = $context['harvest_days_min'];
            $result['harvest_days_max'] = $context['harvest_days_max'];
            $result['yield_data_source'] = $context['yield_data_source'];
            $result['yield_source_publisher'] = $context['yield_source_publisher'];
            $result['yield_source_is_nisr'] = $context['yield_source_is_nisr'];
            $result['yield_source_reference'] = $context['yield_source_reference'];
            $result['yield_observation_years'] = $context['yield_observation_years'];
            $result['yield_first_survey_year'] = $context['yield_first_survey_year'];
            $result['yield_last_survey_year'] = $context['yield_last_survey_year'];
            $result['market_observation_count'] = $context['market_observation_count'];
            $result['market_observation_dates'] = $context['market_observation_dates'];
            $result['market_first_date'] = $context['market_first_date'];
            $result['market_latest_date'] = $context['market_latest_date'];
            $result['market_source_references'] = $context['market_source_references'];
            $result['climate_baseline_score'] = $context['climate_baseline_score'] ?? $context['climate_score'];
            $result['weather_score'] = $context['weather_score'] ?? null;
            $result['rotation_guidance'] = $context['rotation_guidance'];
            $result['soil_stewardship_guidance'] = $context['soil_stewardship_guidance'];
            $result['nutrition_source_note'] = $context['nutrition_source_note'];
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
