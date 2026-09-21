<?php
/**
 * GET /api/foods.php?nutrient=protein_g&category=Legumes&limit=20
 *
 * Returns a JSON list of foods ranked by nutrient delivered per
 * 100 RWF spent. Read-only, no authentication required.
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

$nutrient = $_GET['nutrient'] ?? 'protein_g';
$category = isset($_GET['category']) && $_GET['category'] !== '' ? (string) $_GET['category'] : null;
$limit    = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;

if (!array_key_exists($nutrient, ALLOWED_NUTRIENTS)) {
    http_response_code(400);
    echo json_encode([
        'error'   => 'Unsupported nutrient.',
        'allowed' => array_keys(ALLOWED_NUTRIENTS),
    ]);
    exit;
}

try {
    $pdo = get_db_connection();
    $rows = get_ranked_foods($pdo, $nutrient, $category, $limit);

    echo json_encode([
        'nutrient' => $nutrient,
        'label'    => ALLOWED_NUTRIENTS[$nutrient],
        'category' => $category,
        'count'    => count($rows),
        'results'  => $rows,
    ], JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    error_log('api/foods.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Something went wrong. Please try again.']);
}
