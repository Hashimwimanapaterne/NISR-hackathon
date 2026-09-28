<?php
/**
 * GET /api/food_nutrition.php
 *
 * Returns foods and nutrient values per 100g for the meal planner.
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

try {
    $nutrientColumns = array_keys(ALLOWED_NUTRIENTS);
    $selectColumns = implode(', ', array_map(
        static function (string $column): string {
            return 'np.' . $column;
        },
        $nutrientColumns
    ));
    $stmt = get_db_connection()->query(
        'SELECT f.id, f.name, f.category, ' . $selectColumns . '
         FROM foods f
         INNER JOIN nutrient_profiles np ON np.food_id = f.id
         WHERE f.is_active = 1
         ORDER BY f.name'
    );

    $foods = [];
    foreach ($stmt->fetchAll() as $row) {
        $values = [];
        foreach ($nutrientColumns as $column) {
            $values[$column] = (float) $row[$column];
        }
        $foods[] = [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'category' => $row['category'],
            'nutrients' => $values,
        ];
    }

    $nutrients = [];
    foreach (ALLOWED_NUTRIENTS as $key => $meta) {
        $nutrients[] = [
            'key' => $key,
            'label' => $meta['label'],
            'unit' => $meta['unit'],
        ];
    }

    echo json_encode(['foods' => $foods, 'nutrients' => $nutrients], JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    error_log('api/food_nutrition.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Could not load food nutrition data.']);
}
