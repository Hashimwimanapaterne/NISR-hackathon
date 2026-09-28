<?php
/**
 * GET /api/crop_advisor.php?district_id=21&forecast_season=A-2026
 * forecast_season is optional; defaults to the next available season.
 *
 * Returns ranked crop suggestions for one district, combining soil
 * and climate suitability, soil stewardship, market price momentum,
 * and nutrition-per-hectare via the C++ scoring engine.
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

    $candidates = get_crop_candidates($pdo, $districtId);
    if (empty($candidates)) {
        echo json_encode([
            'district' => $district,
            'results' => [],
            'note' => 'No suitability data recorded for this district yet.',
        ]);
        exit;
    }

    $weather = fetch_district_weather_forecast($district);
    $selectedForecastSeason = $requestedForecastSeason ?? $weather['default_forecast_season'];
    if (!array_key_exists($selectedForecastSeason, $weather['seasonal_forecast'])) {
        http_response_code(400);
        echo json_encode([
            'error' => 'That growing season is outside the available seasonal forecast. Choose an available season.',
            'available_forecast_seasons' => array_keys($weather['seasonal_forecast']),
        ]);
        exit;
    }
    $weather['selected_forecast_season'] = $selectedForecastSeason;
    $weather['selected_season'] = $weather['seasonal_forecast'][$selectedForecastSeason];
    incorporate_forecast_weather($candidates, $weather, $selectedForecastSeason);
    $scored = run_crop_scorer($candidates);
    $scored = merge_candidate_context($scored, $candidates);

    echo json_encode([
        'district' => $district,
        'weather' => $weather,
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
