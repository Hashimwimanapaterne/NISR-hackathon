<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

send_security_headers();

$pdo = get_db_connection();
$categories = get_categories($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Umurima Data — Nutrition per Cost</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header class="site-header">
    <div class="wrap">
        <h1>Which foods give you the most nutrition for your money?</h1>
        <p>Ranking market foods by how much protein, iron, calcium, vitamin A, or energy you get for every 100 RWF you spend — using current market prices.</p>
    </div>
</header>

<main class="wrap">
    <div class="controls">
        <div class="nutrient-tabs" id="nutrient-tabs">
            <?php foreach (ALLOWED_NUTRIENTS as $col => $label): ?>
                <button type="button"
                        data-nutrient="<?= h($col) ?>"
                        class="<?= $col === 'protein_g' ? 'active' : '' ?>">
                    <?= h($label) ?>
                </button>
            <?php endforeach; ?>
        </div>

        <label for="category-select">Category</label>
        <select id="category-select">
            <option value="">All categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= h($cat) ?>"><?= h($cat) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <p class="result-note" id="result-note"></p>

    <table class="ledger">
        <thead>
            <tr>
                <th class="num">#</th>
                <th>Food</th>
                <th class="num">Market price</th>
                <th class="num">Cost / 100g</th>
                <th class="num" id="nutrient-head-label">Nutrient / 100 RWF</th>
            </tr>
        </thead>
        <tbody id="results-body">
            <tr><td colspan="5">Loading…</td></tr>
        </tbody>
    </table>
</main>

<footer class="site-footer">
    Built for the NISR Big Data Hackathon 2026 · Prices shown are sample data unless imported from e-Soko — see README.
</footer>

<script src="assets/js/app.js"></script>
</body>
</html>
