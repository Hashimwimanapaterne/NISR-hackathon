<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

send_security_headers();

$pdo = get_db_connection();
$districts = get_districts_list($pdo);

// Group districts by province for the selector, same pattern as the
// nutrient tool's nutrient groups.
$districtsByProvince = [];
foreach ($districts as $d) {
    $districtsByProvince[$d['province']][] = $d;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Umurima Data — Crop Advisor</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/crop_advisor.css?v=season-weather-1">
</head>
<body>

<header class="site-header">
    <div class="wrap">
        <nav class="module-nav">
            <a href="index.php">Nutrition tool</a>
            <span class="module-nav-current">Crop advisor</span>
        </nav>
        <h1>What should you plant, and where?</h1>
        <p>Compare crops for your district by soil fit, climate fit, market momentum, balanced nutrients in the expected harvest per hectare, and soil stewardship. The nutrition estimate scales all listed nutrients by each crop's expected harvest, then balances energy/macros, minerals, and vitamins. Climate fit combines the district baseline with expected weather for a selectable growing season. Each crop also includes soil-management guidance and a rotation suggestion.</p>
    </div>
</header>

<main class="wrap">
    <aside class="soil-data-note">
        <strong>Soil data status</strong>
        <p>RwaSIS remains the preferred local source. If it has no data for a district, SoilGrids (ISRIC) offers global soil-property maps at 250 m resolution. Crop-fit values, stewardship scores, soil-management notes, rotations, yields, and food nutrient values are illustrative starting points—not validated recommendations. Confirm them with local agronomic guidance and food-composition data before making farm decisions.</p>
        <a href="https://soilgrids.org/" target="_blank" rel="noopener noreferrer">Explore SoilGrids <span aria-hidden="true">↗</span></a>
    </aside>

    <div class="controls-block">
        <label for="district-select">District</label>
        <select id="district-select">
            <option value="">Choose your district…</option>
            <?php foreach ($districtsByProvince as $province => $group): ?>
                <optgroup label="<?= h($province) ?>">
                    <?php foreach ($group as $d): ?>
                        <option value="<?= (int) $d['id'] ?>"><?= h($d['name']) ?></option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>
        </select>
    </div>

    <div id="crop-results">
        <p class="empty-state">Choose a district above to see ranked crop suggestions.</p>
    </div>
</main>

<footer class="site-footer">
    Built for the NISR Big Data Hackathon 2026 · Suitability, stewardship, rotation, and yield guidance are provisional and require local validation; SoilGrids (ISRIC) is an alternative soil-property source — see README.
</footer>

<script src="assets/js/crop_advisor.js?v=season-weather-1"></script>
</body>
</html>
