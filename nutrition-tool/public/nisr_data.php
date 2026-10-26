<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

session_start();
send_security_headers();

function nisr_data_read_csv(string $filename): array
{
    $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . $filename;
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('Could not read the NISR dataset: ' . $filename);
    }
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException('Could not read the NISR dataset: ' . $filename);
    }

    $headers = fgetcsv($handle, null, ',', '"', '');
    if (!is_array($headers)) {
        fclose($handle);
        throw new RuntimeException('The NISR dataset is empty or has an invalid header: ' . $filename);
    }
    $headers = array_map(
        static fn($header): string => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $header)),
        $headers
    );
    $rows = [];
    while (($record = fgetcsv($handle, null, ',', '"', '')) !== false) {
        if ($record === [null] || $record === []) {
            continue;
        }
        if (count($record) !== count($headers)) {
            fclose($handle);
            throw new RuntimeException('A NISR dataset row has an unexpected column count: ' . $filename);
        }
        $row = array_combine($headers, $record);
        if (!is_array($row)) {
            fclose($handle);
            throw new RuntimeException('Could not parse a NISR dataset row: ' . $filename);
        }
        $rows[] = $row;
    }
    fclose($handle);

    return $rows;
}

$pageError = null;
try {
    $yieldRows = nisr_data_read_csv('nisr_sas_2026a_district_yields.csv');
    $fbsRows = nisr_data_read_csv('nisr_fbs_2025_nutrient_supply.csv');
    $cpiRows = nisr_data_read_csv('nisr_cpi_2026_monthly_changes.csv');
    $categoryRows = nisr_data_read_csv('nisr_cpi_2026_urban_category_changes.csv');
} catch (RuntimeException $error) {
    error_log('NISR data explorer could not load its datasets: ' . $error->getMessage());
    $pageError = 'One or more NISR datasets could not be loaded. Please try again later.';
    $yieldRows = $fbsRows = $cpiRows = $categoryRows = [];
}

$districts = array_values(array_unique(array_column($yieldRows, 'district')));
sort($districts, SORT_NATURAL);
$selectedDistrict = (string) ($_GET['district'] ?? '');
if ($selectedDistrict !== '' && !in_array($selectedDistrict, $districts, true)) {
    $selectedDistrict = '';
}
$nutrientOptions = [];
foreach ($fbsRows as $row) {
    $nutrientOptions[$row['nutrient']] = $row['unit'];
}
$selectedNutrient = (string) ($_GET['nutrient'] ?? 'Calcium');
if (!array_key_exists($selectedNutrient, $nutrientOptions)) {
    $selectedNutrient = 'Calcium';
}
$districtYields = array_values(array_filter(
    $yieldRows,
    static fn(array $row): bool => $selectedDistrict === '' || $row['district'] === $selectedDistrict
));
$selectedFbsRows = array_values(array_filter(
    $fbsRows,
    static fn(array $row): bool => $row['nutrient'] === $selectedNutrient
));
$fbsTotal = null;
foreach ($selectedFbsRows as $row) {
    if ($row['product_group_as_reported'] === 'Grand total') {
        $fbsTotal = $row;
        break;
    }
}
$rwandaCpi = array_values(array_filter(
    $cpiRows,
    static fn(array $row): bool => $row['geography'] === 'Rwanda'
));
$rwandaCpiByMonth = [];
foreach ($rwandaCpi as $row) {
    $rwandaCpiByMonth[$row['observation_month']][$row['change_basis']] = $row['change_percent'];
}
krsort($rwandaCpiByMonth);
$latestCpiMonth = (string) array_key_first($rwandaCpiByMonth);
$latestCpi = $rwandaCpiByMonth[$latestCpiMonth] ?? [];
$latestFoodCategory = null;
foreach ($categoryRows as $row) {
    if ($row['report_month'] === 'September' && $row['category_code'] === '01') {
        $latestFoodCategory = $row;
        break;
    }
}
$selectedCpiGeography = (string) ($_GET['cpi_geography'] ?? 'Rwanda');
if (!in_array($selectedCpiGeography, ['Rwanda', 'Urban', 'Rural'], true)) {
    $selectedCpiGeography = 'Rwanda';
}
$selectedCpiRows = array_values(array_filter(
    $cpiRows,
    static fn(array $row): bool => $row['geography'] === $selectedCpiGeography
));
$selectedCpiByMonth = [];
foreach ($selectedCpiRows as $row) {
    $selectedCpiByMonth[$row['observation_month']][$row['change_basis']] = $row['change_percent'];
}
krsort($selectedCpiByMonth);

$yieldComparisonError = null;
$yieldComparisonCrops = [];
$yieldComparisonRows = [];
$yieldComparisonCropId = 0;
$yieldComparisonMax = 0.0;
try {
    $pdo = get_db_connection();
    $cropStatement = $pdo->prepare('
        SELECT DISTINCT c.id, c.name
        FROM crops c
        INNER JOIN district_crop_statistics stats ON stats.crop_id = c.id
        INNER JOIN agricultural_data_sources source ON source.id = stats.data_source_id
        WHERE source.publisher = :publisher
          AND stats.season IN (\'A\', \'B\')
          AND stats.published_yield_kg_per_hectare IS NOT NULL
        ORDER BY c.name
    ');
    $cropStatement->execute([':publisher' => NISR_PUBLISHER]);
    $yieldComparisonCrops = $cropStatement->fetchAll();
    $requestedCropId = isset($_GET['yield_crop']) ? (int) $_GET['yield_crop'] : 0;
    foreach ($yieldComparisonCrops as $cropOption) {
        if ((int) $cropOption['id'] === $requestedCropId) {
            $yieldComparisonCropId = $requestedCropId;
            break;
        }
    }
    if ($yieldComparisonCropId === 0 && $yieldComparisonCrops !== []) {
        $yieldComparisonCropId = (int) $yieldComparisonCrops[0]['id'];
    }

    if ($yieldComparisonCropId > 0) {
        $districtRows = $pdo->query('SELECT id, name FROM districts ORDER BY name')->fetchAll();
        $statisticsStatement = $pdo->prepare('
            SELECT stats.district_id, stats.season, stats.survey_year,
                   stats.published_yield_kg_per_hectare, source.reference_url
            FROM district_crop_statistics stats
            INNER JOIN agricultural_data_sources source ON source.id = stats.data_source_id
            WHERE stats.crop_id = :crop_id
              AND stats.season IN (\'A\', \'B\')
              AND stats.published_yield_kg_per_hectare IS NOT NULL
              AND source.publisher = :publisher
            ORDER BY stats.survey_year DESC
        ');
        $statisticsStatement->execute([
            ':crop_id' => $yieldComparisonCropId,
            ':publisher' => NISR_PUBLISHER,
        ]);
        $statisticsByDistrict = [];
        foreach ($statisticsStatement->fetchAll() as $statistic) {
            $districtId = (int) $statistic['district_id'];
            $season = (string) $statistic['season'];
            if (!isset($statisticsByDistrict[$districtId][$season])) {
                $statisticsByDistrict[$districtId][$season] = $statistic;
            }
        }
        foreach ($districtRows as $districtRow) {
            $districtId = (int) $districtRow['id'];
            $seasonRecords = $statisticsByDistrict[$districtId] ?? [];
            $row = [
                'district' => (string) $districtRow['name'],
                'season_a' => $seasonRecords['A'] ?? null,
                'season_b' => $seasonRecords['B'] ?? null,
            ];
            foreach (['season_a', 'season_b'] as $seasonKey) {
                if ($row[$seasonKey] !== null) {
                    $yieldComparisonMax = max(
                        $yieldComparisonMax,
                        (float) $row[$seasonKey]['published_yield_kg_per_hectare']
                    );
                }
            }
            $yieldComparisonRows[] = $row;
        }
    }
} catch (Throwable $error) {
    error_log('NISR yield comparison could not load: ' . $error->getMessage());
    $yieldComparisonError = 'District yield comparison is temporarily unavailable.';
}

function nisr_data_format_value(string $value, int $decimals = 1): string
{
    return $value === '' ? 'Not reported' : number_format((float) $value, $decimals);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#173d31">
    <title>Data sources — Umurima Data</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/nisr_data.css">
</head>
<body>
<header class="site-header">
    <nav class="topbar wrap" aria-label="Main navigation">
        <a class="brand" href="index.php" aria-label="Umurima Data home">
            <span class="brand-mark" aria-hidden="true">U</span>
            <span>umurima<span class="brand-light">data</span></span>
        </a>
        <a class="nav-link" href="index.php">Nutrition tool <span aria-hidden="true">↗</span></a>
        <a class="nav-link" href="crop_advisor.php">Crop advisor <span aria-hidden="true">↗</span></a>
        <span class="nav-link" aria-current="page">Data sources</span>
    </nav>
    <div class="wrap data-hero">
        <p class="section-kicker">Official Rwanda statistics</p>
        <h1>Explore data sources</h1>
        <p>Browse district crop yields and national food-supply and price-index context, with units and source details preserved.</p>
    </div>
</header>

<main class="wrap nisr-data">
    <?php if ($pageError !== null): ?>
        <p class="nisr-error" role="alert"><?= h($pageError) ?></p>
    <?php else: ?>
        <section class="nisr-panel" id="season-a-yields">
            <div class="nisr-panel-heading">
                <div>
                    <p class="section-kicker">SAS 2026 · Season A · Table 13</p>
                    <h2>Average district crop yields</h2>
                    <p>Reported averages in kg/ha. Missing source dashes are shown as “Not reported,” never as zero.</p>
                </div>
                <a href="assets/SAS%202026%20Season%20A%20_Final%20Report.pdf" target="_blank" rel="noopener noreferrer">Open NISR report ↗</a>
            </div>
            <form class="nisr-filter" method="get" action="nisr_data.php#season-a-yields">
                <label for="district-filter">District</label>
                <select id="district-filter" name="district">
                    <option value="">All districts</option>
                    <?php foreach ($districts as $district): ?>
                        <option value="<?= h($district) ?>"<?= $selectedDistrict === $district ? ' selected' : '' ?>><?= h($district) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="nutrient" value="<?= h($selectedNutrient) ?>">
                <button type="submit">Show yields</button>
            </form>
            <div class="nisr-table-scroll">
                <table class="nisr-table">
                    <thead><tr><th scope="col">District</th><th scope="col">Crop category</th><th scope="col">Yield (kg/ha)</th><th scope="col">App mapping</th></tr></thead>
                    <tbody>
                    <?php foreach ($districtYields as $row): ?>
                        <tr>
                            <th scope="row"><?= h($row['district']) ?></th>
                            <td><?= h($row['crop_as_reported']) ?></td>
                            <td><?= $row['value_status'] === 'reported' ? h(nisr_data_format_value($row['yield_kg_per_ha'], 0)) : 'Not reported' ?></td>
                            <td><?= $row['crop_for_app'] !== '' ? h($row['crop_for_app']) : 'Not mapped for scoring' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="nisr-source">Source: NISR, <em>Seasonal Agricultural Survey, Season A, March 2026</em>, Table 13, printed p. 35. Licensed CC BY 4.0.</p>
        </section>

        <section class="nisr-panel" id="district-comparison">
            <div class="nisr-panel-heading">
                <div>
                    <p class="section-kicker">NISR SAS 2026 · Seasons A and B</p>
                    <h2>District yield comparison</h2>
                    <p>Compare published yields for one crop across all districts. Missing observations are labeled as not reported, never treated as zero.</p>
                </div>
            </div>
            <?php if ($yieldComparisonError !== null): ?>
                <p class="nisr-error" role="alert"><?= h($yieldComparisonError) ?></p>
            <?php elseif ($yieldComparisonCrops === []): ?>
                <p class="nisr-error" role="status">No published NISR district-yield data are available for comparison.</p>
            <?php else: ?>
                <?php
                $selectedYieldCrop = '';
                foreach ($yieldComparisonCrops as $cropOption) {
                    if ((int) $cropOption['id'] === $yieldComparisonCropId) {
                        $selectedYieldCrop = (string) $cropOption['name'];
                        break;
                    }
                }
                $reportedDistrictCount = count(array_filter(
                    $yieldComparisonRows,
                    static fn(array $row): bool => $row['season_a'] !== null || $row['season_b'] !== null
                ));
                ?>
                <form class="nisr-filter" method="get" action="nisr_data.php#district-comparison">
                    <label for="yield-crop-filter">Crop</label>
                    <select id="yield-crop-filter" name="yield_crop">
                        <?php foreach ($yieldComparisonCrops as $cropOption): ?>
                            <option value="<?= (int) $cropOption['id'] ?>"<?= (int) $cropOption['id'] === $yieldComparisonCropId ? ' selected' : '' ?>><?= h($cropOption['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="district" value="<?= h($selectedDistrict) ?>">
                    <input type="hidden" name="nutrient" value="<?= h($selectedNutrient) ?>">
                    <input type="hidden" name="cpi_geography" value="<?= h($selectedCpiGeography) ?>">
                    <button type="submit">Compare districts</button>
                </form>
                <p class="yield-chart-scale">
                    <?= h($selectedYieldCrop) ?> · <?= $reportedDistrictCount ?> of <?= count($yieldComparisonRows) ?> districts have at least one published season value.
                    Maximum reported yield: <?= h(nisr_data_format_value((string) $yieldComparisonMax, 0)) ?> kg/ha.
                </p>
                <div class="yield-chart-legend" aria-label="Chart legend">
                    <span><i class="yield-season-a" aria-hidden="true"></i> Season A 2026</span>
                    <span><i class="yield-season-b" aria-hidden="true"></i> Season B 2026</span>
                </div>
                <div class="yield-chart" role="list" aria-label="<?= h($selectedYieldCrop) ?> yield by district and season">
                    <?php foreach ($yieldComparisonRows as $row): ?>
                        <div class="yield-chart-row" role="listitem">
                            <strong><?= h($row['district']) ?></strong>
                            <div class="yield-chart-seasons">
                                <?php foreach (['season_a' => ['A', 'Season A'], 'season_b' => ['B', 'Season B']] as $seasonKey => [$seasonLetter, $seasonLabel]): ?>
                                    <?php $record = $row[$seasonKey]; ?>
                                    <div class="yield-chart-series">
                                        <span class="yield-chart-season-label">S<?= h($seasonLetter) ?></span>
                                        <?php if ($record === null): ?>
                                            <span class="yield-chart-missing">Not reported</span>
                                        <?php else: ?>
                                            <?php
                                            $yield = (float) $record['published_yield_kg_per_hectare'];
                                            $barWidth = $yieldComparisonMax > 0 ? min(100.0, ($yield / $yieldComparisonMax) * 100.0) : 0.0;
                                            ?>
                                            <span class="yield-chart-track" aria-hidden="true"><span class="yield-chart-bar <?= $seasonLetter === 'A' ? 'yield-season-a' : 'yield-season-b' ?>" style="width: <?= h(number_format($barWidth, 2, '.', '')) ?>%"></span></span>
                                            <span class="yield-chart-value"><?= h(nisr_data_format_value((string) $record['published_yield_kg_per_hectare'], 0)) ?> kg/ha</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="nisr-source">
                    Source: NISR <em>Seasonal Agricultural Survey, Season A 2026</em>, Table 13, and
                    <em>Season B 2026</em>, Table 11. Licensed CC BY 4.0.
                    <a href="assets/SAS%202026%20Season%20A%20_Final%20Report.pdf" target="_blank" rel="noopener noreferrer">Season A report</a> ·
                    <a href="assets/SAS%202026B_Main%20report.pdf" target="_blank" rel="noopener noreferrer">Season B report</a>.
                    Bars share a crop-specific scale; values are shown in kg/ha. These are historical survey observations, not forecast yields.
                </p>
            <?php endif; ?>
        </section>

        <section class="nisr-panel" id="food-supply">
            <div class="nisr-panel-heading">
                <div>
                    <p class="section-kicker">Food Balance Sheet 2025 · Annexes 2–3</p>
                    <h2>National nutrient supply contribution</h2>
                    <p>Daily per-capita supply contribution by product group—not composition per 100 g of an individual food.</p>
                </div>
                <a href="assets/Food%20Balance%20Sheets%202025%20Report.pdf" target="_blank" rel="noopener noreferrer">Open NISR report ↗</a>
            </div>
            <form class="nisr-filter" method="get" action="nisr_data.php#food-supply">
                <label for="nutrient-filter">Nutrient</label>
                <select id="nutrient-filter" name="nutrient">
                    <?php foreach ($nutrientOptions as $nutrient => $unit): ?>
                        <option value="<?= h($nutrient) ?>"<?= $selectedNutrient === $nutrient ? ' selected' : '' ?>><?= h($nutrient) ?> (<?= h($unit) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="district" value="<?= h($selectedDistrict) ?>">
                <button type="submit">Show nutrient</button>
            </form>
            <?php if ($fbsTotal !== null): ?>
                <p class="nisr-total"><strong><?= h($selectedNutrient) ?>:</strong>
                    <?= h(nisr_data_format_value($fbsTotal['value_per_capita_per_day'])) ?> <?= h($fbsTotal['unit']) ?> per person per day (national total)</p>
            <?php endif; ?>
            <div class="nisr-table-scroll">
                <table class="nisr-table">
                    <thead><tr><th scope="col">Product/group as reported</th><th scope="col"><?= h($selectedNutrient) ?> supply</th><th scope="col">Unit</th></tr></thead>
                    <tbody>
                    <?php foreach ($selectedFbsRows as $row): ?>
                        <tr>
                            <th scope="row"><?= h($row['product_group_as_reported']) ?></th>
                            <td><?= $row['value_status'] === 'reported' ? h(nisr_data_format_value($row['value_per_capita_per_day'])) : 'Not reported' ?></td>
                            <td><?= h($row['unit']) ?> per person per day</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="nisr-source">Source: NISR, <em>Food Balance Sheet 2025</em>, Annex 2 (printed pp. 35–38) and Annex 3 (pp. 39–42). Product groups include subtotals; do not sum them as if mutually exclusive. Licensed CC BY 4.0.</p>
        </section>

        <section class="nisr-panel" id="cpi-context">
            <div class="nisr-panel-heading">
                <div>
                    <p class="section-kicker">Consumer Price Index · August–September 2026</p>
                    <h2>Price-change context</h2>
                    <p>CPI percentage changes and expenditure weights are aggregate index measures, not food prices in RWF.</p>
                </div>
                <a href="assets/SEPTEMBER%202026%20CPI%20Report%20English.pdf" target="_blank" rel="noopener noreferrer">Open NISR report ↗</a>
            </div>
            <div class="nisr-cpi-highlights">
                <div><span>Rwanda CPI, annual change (Sep 2026)</span><strong><?= h($latestCpi['year_over_year'] ?? 'Not available') ?>%</strong></div>
                <div><span>Rwanda CPI, monthly change (Sep 2026)</span><strong><?= h($latestCpi['month_over_month'] ?? 'Not available') ?>%</strong></div>
                <?php if ($latestFoodCategory !== null): ?>
                    <div><span>Urban food and non-alcoholic beverages, annual change</span><strong><?= h($latestFoodCategory['year_over_year_change_percent']) ?>%</strong></div>
                <?php endif; ?>
            </div>
            <h3><?= h($selectedCpiGeography) ?> CPI trend</h3>
            <form class="nisr-filter" method="get" action="nisr_data.php#cpi-context">
                <label for="cpi-geography-filter">Index geography</label>
                <select id="cpi-geography-filter" name="cpi_geography">
                    <?php foreach (['Rwanda', 'Urban', 'Rural'] as $geography): ?>
                        <option value="<?= h($geography) ?>"<?= $selectedCpiGeography === $geography ? ' selected' : '' ?>><?= h($geography) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="district" value="<?= h($selectedDistrict) ?>">
                <input type="hidden" name="nutrient" value="<?= h($selectedNutrient) ?>">
                <button type="submit">Show CPI</button>
            </form>
            <div class="nisr-table-scroll">
                <table class="nisr-table">
                    <thead><tr><th scope="col"><?= h($selectedCpiGeography) ?> · month</th><th scope="col">Annual change (%)</th><th scope="col">Monthly change (%)</th></tr></thead>
                    <tbody>
                    <?php foreach (array_reverse($selectedCpiByMonth, true) as $month => $values): ?>
                        <tr><th scope="row"><?= h($month) ?></th><td><?= h($values['year_over_year'] ?? 'Not reported') ?></td><td><?= h($values['month_over_month'] ?? 'Not reported') ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <h3>Urban category changes · August and September 2026</h3>
            <div class="nisr-table-scroll">
                <table class="nisr-table">
                    <thead><tr><th scope="col">Report month</th><th scope="col">Code</th><th scope="col">Category</th><th scope="col">Weight (%)</th><th scope="col">Monthly change (%)</th><th scope="col">Annual change (%)</th></tr></thead>
                    <tbody>
                    <?php foreach ($categoryRows as $row): ?>
                        <tr><td><?= h($row['report_month']) ?></td><td><?= h($row['category_code'] !== '' ? $row['category_code'] : '—') ?></td><th scope="row"><?= h($row['category']) ?></th><td><?= h($row['weight_percent']) ?></td><td><?= h($row['month_over_month_change_percent']) ?></td><td><?= h($row['year_over_year_change_percent']) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="nisr-source">Sources: NISR CPI August 2026 and September 2026 reports, Tables 1–2, printed pp. 4–5. Licensed CC BY 4.0. The CPI series contextualizes inflation and cannot replace dated, local food-price observations.</p>
            <p class="nisr-report-links">
                <a href="assets/CONSUMER%20PRICE%20INDEX%20%20(CPI)%20AUGUST%202026.pdf" target="_blank" rel="noopener noreferrer">August 2026 CPI report ↗</a>
                <a href="assets/SEPTEMBER%202026%20CPI%20Report%20English.pdf" target="_blank" rel="noopener noreferrer">September 2026 CPI report ↗</a>
            </p>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
