<?php
/**
 * Core domain logic for the Nutrition-per-Cost tool.
 */

declare(strict_types=1);

// Whitelist of nutrients users may rank by. Never build SQL from
// raw user input — always map through this list first.
const ALLOWED_NUTRIENTS = [
    'calories_kcal' => 'Calories',
    'protein_g'     => 'Protein',
    'iron_mg'       => 'Iron',
    'calcium_mg'    => 'Calcium',
    'vitamin_a_ug'  => 'Vitamin A',
];

/**
 * Rank foods by how much of a given nutrient you get per 100 RWF spent.
 *
 * @param PDO         $pdo
 * @param string      $nutrientColumn  Must be a key of ALLOWED_NUTRIENTS.
 * @param string|null $category        Optional exact-match category filter.
 * @param int         $limit           Max rows to return (1-100).
 * @return array<int, array<string, mixed>>
 */
function get_ranked_foods(PDO $pdo, string $nutrientColumn, ?string $category, int $limit = 20): array
{
    if (!array_key_exists($nutrientColumn, ALLOWED_NUTRIENTS)) {
        throw new InvalidArgumentException('Unsupported nutrient column.');
    }

    $limit = max(1, min(100, $limit));

    // grams_per_unit lets us normalise any unit_label (kg, litre,
    // piece, tray) to a cost per 100g, so every food is compared
    // on the same basis regardless of how it's sold.
    $sql = "
        SELECT
            f.id,
            f.name,
            f.category,
            f.unit_label,
            lp.price_rwf,
            lp.market_name,
            lp.recorded_on,
            np.{$nutrientColumn} AS nutrient_value,
            ROUND((lp.price_rwf / f.grams_per_unit) * 100, 2) AS cost_per_100g,
            CASE
                WHEN lp.price_rwf > 0
                THEN ROUND(np.{$nutrientColumn} / ((lp.price_rwf / f.grams_per_unit) * 100) * 100, 3)
                ELSE NULL
            END AS nutrient_per_100rwf
        FROM foods f
        INNER JOIN nutrient_profiles np ON np.food_id = f.id
        INNER JOIN latest_prices lp     ON lp.food_id = f.id
        WHERE f.is_active = 1
    ";

    $params = [];
    if ($category !== null && $category !== '') {
        $sql .= ' AND f.category = :category';
        $params[':category'] = $category;
    }

    $sql .= ' ORDER BY nutrient_per_100rwf DESC LIMIT :limit';

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/** Return the distinct list of food categories, for the filter dropdown. */
function get_categories(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT DISTINCT category FROM foods WHERE is_active = 1 ORDER BY category');
    return array_column($stmt->fetchAll(), 'category');
}

/** Safely read a value from an associative array with a default fallback. */
function arr_get(array $data, string $key, $default = null)
{
    return array_key_exists($key, $data) ? $data[$key] : $default;
}

/** HTML-escape helper to keep templates readable and consistently safe. */
function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
