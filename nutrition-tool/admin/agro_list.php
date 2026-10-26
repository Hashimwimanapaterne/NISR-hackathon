<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

start_secure_session();
send_security_headers();
require_admin_login();

$pdo = get_db_connection();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_scoring_dataset') {
    verify_csrf((string) ($_POST['csrf_token'] ?? ''));
    $sourceId = filter_var($_POST['source_id'] ?? null, FILTER_VALIDATE_INT);
    $isActive = filter_var($_POST['is_active'] ?? null, FILTER_VALIDATE_INT);
    if (!is_int($sourceId) || $sourceId < 1 || !in_array($isActive, [0, 1], true)) {
        http_response_code(400);
        exit('Invalid dataset scoring setting.');
    }
    $sourceCheck = $pdo->prepare('SELECT id, publisher FROM agricultural_data_sources WHERE id = :id');
    $sourceCheck->execute([':id' => $sourceId]);
    $source = $sourceCheck->fetch();
    if ($source === false) {
        http_response_code(404);
        exit('Dataset not found.');
    }
    if ($source['publisher'] === 'National Institute of Statistics of Rwanda (NISR)') {
        http_response_code(400);
        exit('NISR yield data is prioritized automatically and cannot be disabled here.');
    }
    try {
        $updateSource = $pdo->prepare(
            'UPDATE agricultural_data_sources SET is_active_for_scoring = :is_active WHERE id = :id'
        );
        $updateSource->execute([':is_active' => $isActive, ':id' => $sourceId]);
    } catch (Throwable $error) {
        error_log('Could not update crop statistics dataset scoring: ' . $error->getMessage());
        http_response_code(500);
        exit('Dataset scoring setting failed. No changes were saved.');
    }
    header('Location: agro_list.php?msg=' . rawurlencode(
        $isActive === 1 ? 'Public fallback dataset enabled.' : 'Public fallback dataset disabled.'
    ));
    exit;
}

$rows = $pdo->query("
    SELECT a.id, d.name AS district, d.province, c.name AS crop,
           a.soil_score, a.climate_score, a.erosion_control_score,
           a.nutrient_balance_score, a.soil_structure_score,
           a.erosion_risk, a.source,
           a.source_reference, a.updated_at
    FROM agro_suitability a
    INNER JOIN districts d ON d.id = a.district_id
    INNER JOIN crops c ON c.id = a.crop_id
    ORDER BY d.province, d.name, c.name
")->fetchAll();
$illustrativeRows = count(array_filter($rows, static fn(array $row): bool => $row['source'] === 'illustrative_estimate'));
$missingCitations = count(array_filter($rows, static fn(array $row): bool => empty($row['source_reference'])));
$datasets = $pdo->query('
    SELECT s.id, s.dataset_title, s.publisher, s.release_year, s.citation,
           s.reference_url, s.reuse_terms, s.imported_at, s.is_active_for_scoring,
           (SELECT COUNT(*) FROM district_crop_statistics stats WHERE stats.data_source_id = s.id)
               AS yield_observation_count,
           (SELECT COUNT(*) FROM agricultural_survey_observations observations
            WHERE observations.data_source_id = s.id) AS reference_observation_count
    FROM agricultural_data_sources s
    ORDER BY s.imported_at DESC
')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin — Crop suitability</title>
    <link rel="stylesheet" href="../public/assets/css/style.css">
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body class="admin-body">

<nav class="admin-nav">
    <a href="index.php">Foods</a>
    <a href="agro_list.php">Crop suitability</a>
    <a href="pdf_extract.php">PDF extraction</a>
    <span class="spacer"></span>
    <span>Signed in as <?= h($_SESSION['admin_username'] ?? '') ?></span>
    <a href="logout.php">Sign out</a>
</nav>

<main class="wrap">
    <h1>District × crop suitability</h1>
    <p class="result-note">
        Soil fit, climate fit, and stewardship indicators feed the Crop Advisor. When RwaSIS data
        is unavailable, SoilGrids (ISRIC) can provide mapped soil properties. Derive suitability
        and stewardship scores using local agronomic guidance and record data provenance. SoilGrids
        does not itself provide crop-suitability scores.
    </p>
    <section class="admin-summary" aria-label="Suitability data provenance summary">
        <div class="summary-item"><span>District × crop records</span><strong><?= count($rows) ?></strong></div>
        <div class="summary-item"><span>Illustrative records (not decision-ready)</span><strong><?= $illustrativeRows ?></strong></div>
        <div class="summary-item"><span>Missing source citations</span><strong><?= $missingCitations ?></strong></div>
    </section>

    <section class="form-panel" aria-labelledby="agricultural-datasets-title">
        <h2 id="agricultural-datasets-title">Imported agricultural datasets</h2>
        <p class="form-help">NISR district observations are prioritized automatically wherever they match. Review each source's citation and reuse terms before enabling one or more verified public datasets as fallbacks; for each crop and season, the newest enabled source with usable data is used only when NISR has no matching yield. Overlapping sources are never averaged together.</p>
        <?php if ($datasets === []): ?>
            <p>No aggregated crop datasets have been imported. See the CLI importer and required aggregation safeguards in the project README.</p>
        <?php else: ?>
            <div class="admin-table-shell">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th scope="col">Dataset</th>
                            <th scope="col">Publisher / release</th>
                            <th scope="col">Source and reuse terms</th>
                            <th scope="col">Reference / yield records</th>
                            <th scope="col">Imported</th>
                            <th scope="col">Scoring</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($datasets as $dataset): ?>
                        <tr>
                            <td><?= h($dataset['dataset_title']) ?></td>
                            <td><?= h($dataset['publisher']) ?> · <?= (int) $dataset['release_year'] ?></td>
                            <td>
                                <?= h($dataset['citation']) ?>
                                <span class="food-meta"><?= h($dataset['reuse_terms']) ?></span>
                                <a href="<?= h($dataset['reference_url']) ?>" target="_blank" rel="noopener noreferrer">Dataset reference ↗</a>
                            </td>
                            <td>
                                <?= number_format((int) $dataset['reference_observation_count']) ?> reference
                                <span class="food-meta"><?= number_format((int) $dataset['yield_observation_count']) ?> yield records</span>
                            </td>
                            <td><?= h(substr((string) $dataset['imported_at'], 0, 10)) ?></td>
                            <td>
                                <?php if ($dataset['publisher'] === 'National Institute of Statistics of Rwanda (NISR)'): ?>
                                    <strong>NISR priority</strong>
                                <?php else: ?>
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_scoring_dataset">
                                        <input type="hidden" name="source_id" value="<?= (int) $dataset['id'] ?>">
                                        <input type="hidden" name="is_active" value="<?= (bool) $dataset['is_active_for_scoring'] ? '0' : '1' ?>">
                                        <button type="submit"><?= (bool) $dataset['is_active_for_scoring'] ? 'Disable fallback' : 'Enable fallback' ?></button>
                                    </form>
                                    <span class="food-meta"><?= (bool) $dataset['is_active_for_scoring'] ? 'Enabled fallback' : 'Not enabled' ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <?php if (isset($_GET['msg'])): ?>
        <p class="alert-success"><?= h((string) $_GET['msg']) ?></p>
    <?php endif; ?>

    <table class="admin-table">
        <thead>
            <tr>
                <th>Province</th>
                <th>District</th>
                <th>Crop</th>
                <th>Soil</th>
                <th>Climate</th>
                <th>Erosion protection</th>
                <th>Nutrient balance</th>
                <th>Soil structure</th>
                <th>Erosion</th>
                <th>Source</th>
                <th>Updated</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= h($r['province']) ?></td>
                <td><?= h($r['district']) ?></td>
                <td><?= h($r['crop']) ?></td>
                <td><?= h((string) $r['soil_score']) ?></td>
                <td><?= h((string) $r['climate_score']) ?></td>
                <td><?= h((string) $r['erosion_control_score']) ?></td>
                <td><?= h((string) $r['nutrient_balance_score']) ?></td>
                <td><?= h((string) $r['soil_structure_score']) ?></td>
                <td><?= h($r['erosion_risk']) ?></td>
                <td>
                    <?php
                    $sourceLabels = [
                        'rwasis_import' => 'RwaSIS (RAB)',
                        'soilgrids_isric' => 'SoilGrids (ISRIC)',
                        'nisr_import' => 'NISR dataset',
                        'other_public_dataset' => 'Other public dataset',
                        'illustrative_estimate' => 'Illustrative estimate',
                    ];
                    echo h($sourceLabels[$r['source']] ?? $r['source']);
                    ?>
                    <?php if (!empty($r['source_reference'])): ?>
                        <span class="food-meta"><?= h($r['source_reference']) ?></span>
                    <?php endif; ?>
                </td>
                <td><?= h(substr((string) $r['updated_at'], 0, 10)) ?></td>
                <td><a href="agro_form.php?id=<?= (int) $r['id'] ?>">Edit</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</main>
</body>
</html>
