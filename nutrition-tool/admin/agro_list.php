<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

start_secure_session();
send_security_headers();
require_admin_login();

$pdo = get_db_connection();
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
