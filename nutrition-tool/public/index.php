<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

send_security_headers();

$pdo = get_db_connection();
$categories = get_categories($pdo);
$nutrientGroups = get_nutrient_groups();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#173d31">
    <meta name="description" content="Compare the nutrition in everyday foods with their market prices. Find more protein, iron, and other nutrients for every 100 RWF.">
    <title>Umurima Data — Eat well for less</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header class="site-header">
    <nav class="topbar wrap" aria-label="Main navigation">
        <a class="brand" href="index.php" aria-label="Umurima Data home">
            <span class="brand-mark" aria-hidden="true">U</span>
            <span>umurima<span class="brand-light">data</span></span>
        </a>
        <a class="nav-link" href="../admin/login.php">Admin sign in <span aria-hidden="true">↗</span></a>
    </nav>
    <div class="hero wrap">
        <p class="eyebrow"><span class="eyebrow-dot"></span> Nutrition meets the market</p>
        <h1>Eat well.<br><em>Spend wisely.</em></h1>
        <p class="hero-copy">Find the foods that give you more of what your body needs for every 100 RWF. Compare everyday market foods by nutrition and price.</p>
        <div class="hero-meta">
            <span class="hero-meta-icon" aria-hidden="true">↗</span>
            Ranked using the latest recorded prices
        </div>
    </div>
</header>

<main class="wrap">
    <section class="explorer" aria-labelledby="explorer-title">
        <div class="section-heading">
            <div>
                <p class="section-kicker">The food finder</p>
                <h2 id="explorer-title">Make every franc count</h2>
                <p class="section-copy">Choose a nutrient to see which foods offer the best value.</p>
            </div>
            <span class="unit-badge">Value per 100 RWF</span>
        </div>

        <div class="controls-block" id="controls-block">
            <?php foreach ($nutrientGroups as $groupName => $cols): ?>
                <fieldset class="nutrient-group">
                    <legend class="nutrient-group-label"><?= h($groupName) ?></legend>
                    <div class="nutrient-tabs" data-group>
                        <?php foreach ($cols as $col => $meta): ?>
                            <button type="button"
                                    data-nutrient="<?= h($col) ?>"
                                    aria-pressed="<?= $col === 'protein_g' ? 'true' : 'false' ?>"
                                    class="<?= $col === 'protein_g' ? 'active' : '' ?>">
                                <?= h($meta['label']) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
            <?php endforeach; ?>

            <div class="filter-row">
                <label class="filter-control" for="category-select">
                    <span>Food category</span>
                    <select id="category-select">
                        <option value="">All categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= h($cat) ?>"><?= h($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="filter-control search-control" for="food-search">
                    <span>Find a food</span>
                    <input id="food-search" type="search" maxlength="100" placeholder="e.g. beans" autocomplete="off">
                </label>
                <button class="reset-button" id="reset-filters" type="button">Reset filters</button>
            </div>
        </div>

        <div class="results-heading">
            <div>
                <p class="section-kicker">Your results</p>
                <h2 id="results-title">Best value foods</h2>
            </div>
            <div class="results-summary">
                <span class="status-dot" aria-hidden="true"></span>
                <span id="results-count" role="status" aria-live="polite">Loading foods…</span>
            </div>
        </div>

        <p class="result-note" id="result-note">Loading the latest available food and price data.</p>

        <div class="table-shell" id="table-shell">
            <table class="ledger">
                <caption class="sr-only">Foods ranked by nutrient value per 100 RWF spent</caption>
                <thead>
                    <tr>
                        <th class="rank-heading" scope="col">Rank</th>
                        <th scope="col">Food</th>
                        <th class="num" scope="col">Market price</th>
                        <th class="num" scope="col">Cost / 100g</th>
                        <th class="num nutrient-heading" id="nutrient-head-label" scope="col">Nutrient / 100 RWF</th>
                    </tr>
                </thead>
                <tbody id="results-body" aria-busy="true">
                    <tr><td colspan="5"><div class="loading-state"><span class="loader" aria-hidden="true"></span>Finding the best value foods…</div></td></tr>
                </tbody>
            </table>
        </div>

        <aside class="method-note">
            <span class="method-icon" aria-hidden="true">i</span>
            <p><strong>How to read this:</strong> Foods are compared by the amount of the selected nutrient you get for 100 RWF. Cost per 100g helps compare foods sold in different units. Nutrition values are per 100g edible portion.</p>
        </aside>
    </section>
</main>

<footer class="site-footer">
    <div class="footer-inner wrap">
        <a class="brand footer-brand" href="index.php"><span class="brand-mark" aria-hidden="true">U</span><span>umurima<span class="brand-light">data</span></span></a>
        <p>Built for the NISR Big Data Hackathon 2026.<br><span>Prices are sample data unless imported from e-Soko.</span></p>
        <a href="../README.md">About this project <span aria-hidden="true">↗</span></a>
    </div>
</footer>

<script src="assets/js/app.js"></script>
</body>
</html>
