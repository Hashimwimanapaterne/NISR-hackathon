<?php
/**
 * GET /api/crop_advisor.php?district_id=21&forecast_season=A-2026
 * forecast_season is optional; the default is the current Rwanda season.
 *
 * Returns ranked crop suggestions for one district using the available,
 * cited dimensions. Missing complete soil or stewardship data is excluded from
 * the composite; climate fit uses the selected public seasonal weather forecast.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

send_security_headers();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$districtId = isset($_GET['district_id']) ? (int) $_GET['district_id'] : 0;
$requestedForecastSeason = isset($_GET['forecast_season']) ? (string) $_GET['forecast_season'] : null;
if ($districtId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'A valid district_id is required.']);
    exit;
}
if ($requestedForecastSeason !== null
    && !preg_match('/^[ABC]-\d{4}$/D', $requestedForecastSeason)
) {
    http_response_code(400);
    echo json_encode(['error' => 'forecast_season must identify a valid season, such as A-2026.']);
    exit;
}

try {
    $pdo = get_db_connection();

    $stmt = $pdo->prepare('SELECT id, name, province FROM districts WHERE id = :id');
    $stmt->execute([':id' => $districtId]);
    $district = $stmt->fetch();
    if ($district === false) {
        http_response_code(404);
        echo json_encode(['error' => 'District not found.']);
        exit;
    }

    $selectedSeason = $requestedForecastSeason ?? current_rwanda_growing_season();
    $season = $selectedSeason[0];
    $candidates = get_crop_candidates($pdo, $districtId, $season);
    if (empty($candidates)) {
        echo json_encode([
            'district' => $district,
            'results' => [],
            'note' => 'No crops with usable nutrient profiles and yield evidence are available for this district and season yet.',
        ]);
        exit;
    }
    $weather = fetch_district_weather_forecast($district);
    $defaultSeason = array_key_exists($selectedSeason, $weather['seasonal_forecast'])
        ? $selectedSeason
        : $weather['default_forecast_season'];
    $selectedForecastSeason = $requestedForecastSeason ?? $defaultSeason;
    if (!array_key_exists($selectedForecastSeason, $weather['seasonal_forecast'])) {
        http_response_code(400);
        echo json_encode([
            'error' => 'That growing season is outside the available seasonal forecast. Choose an available season.',
            'available_forecast_seasons' => array_keys($weather['seasonal_forecast']),
        ]);
        exit;
    }
    $selectedSeason = $selectedForecastSeason;
    if ($selectedSeason[0] !== $season) {
        $season = $selectedSeason[0];
        $candidates = get_crop_candidates($pdo, $districtId, $season);
        if (empty($candidates)) {
            echo json_encode([
                'district' => $district,
                'results' => [],
                'note' => 'No crops with usable nutrient profiles and yield evidence are available for this district and season yet.',
                'yield_season' => $selectedSeason,
            ]);
            exit;
        }
    }
    $weather['selected_forecast_season'] = $selectedForecastSeason;
    $weather['selected_season'] = $weather['seasonal_forecast'][$selectedForecastSeason];
    incorporate_forecast_weather($candidates, $weather, $selectedForecastSeason);
    $scoringWeights = available_crop_scoring_weights($candidates);

    $scored = run_crop_scorer($candidates, $scoringWeights);
    $scored = merge_candidate_context($scored, $candidates);

    echo json_encode([
        'district' => $district,
        'weather' => $weather,
        'yield_season' => $selectedSeason,
        'results' => $scored,
    ], JSON_PRETTY_PRINT);

} catch (RuntimeException $e) {
    // Errors from the scoring engine itself — message is already safe to show.
    http_response_code(503);
    echo json_encode(['error' => $e->getMessage()]);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('api/crop_advisor.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Something went wrong. Please try again.']);
}
