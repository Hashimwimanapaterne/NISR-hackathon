<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

start_secure_session();
send_security_headers();
require_admin_login();

$pdo = get_db_connection();

$id = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id']) ? (int) $_POST['id'] : 0);
$errors = [];

$stmt = $pdo->prepare('
    SELECT a.*, d.name AS district_name, c.name AS crop_name
    FROM agro_suitability a
    INNER JOIN districts d ON d.id = a.district_id
    INNER JOIN crops c ON c.id = a.crop_id
    WHERE a.id = :id
');
$stmt->execute([':id' => $id]);
$row = $stmt->fetch();
if ($row === false) {
    http_response_code(404);
    die('Suitability record not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? '');

    $soil = (float) ($_POST['soil_score'] ?? -1);
    $climate = (float) ($_POST['climate_score'] ?? -1);
    $erosionControl = (float) ($_POST['erosion_control_score'] ?? -1);
    $nutrientBalance = (float) ($_POST['nutrient_balance_score'] ?? -1);
    $soilStructure = (float) ($_POST['soil_structure_score'] ?? -1);
    $erosion = (string) ($_POST['erosion_risk'] ?? '');
    $fertNote = trim((string) ($_POST['fertilizer_note'] ?? ''));
    $source = (string) ($_POST['source'] ?? 'illustrative_estimate');
    $sourceReference = trim((string) ($_POST['source_reference'] ?? ''));

    if ($soil < 0 || $soil > 100) $errors[] = 'Soil score must be between 0 and 100.';
    if ($climate < 0 || $climate > 100) $errors[] = 'Climate score must be between 0 and 100.';
    if ($erosionControl < 0 || $erosionControl > 100) $errors[] = 'Erosion protection score must be between 0 and 100.';
    if ($nutrientBalance < 0 || $nutrientBalance > 100) $errors[] = 'Nutrient balance score must be between 0 and 100.';
    if ($soilStructure < 0 || $soilStructure > 100) $errors[] = 'Soil structure score must be between 0 and 100.';
    if (!in_array($erosion, ['low', 'medium', 'high'], true)) $errors[] = 'Erosion risk must be low, medium, or high.';
    if (!in_array($source, ['illustrative_estimate', 'rwasis_import', 'soilgrids_isric'], true)) $errors[] = 'Invalid source value.';
    if (strlen($sourceReference) > 255) $errors[] = 'Source reference must be 255 characters or fewer.';
    if ($source !== 'illustrative_estimate' && $sourceReference === '') {
        $errors[] = 'Add a citation or dataset reference for imported soil data.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('
            UPDATE agro_suitability
            SET soil_score = :soil, climate_score = :climate,
                erosion_control_score = :erosion_control,
                nutrient_balance_score = :nutrient_balance,
                soil_structure_score = :soil_structure, erosion_risk = :erosion,
                fertilizer_note = :fert, source = :source, source_reference = :source_reference
            WHERE id = :id
        ');
        $stmt->execute([
            ':soil' => $soil, ':climate' => $climate,
            ':erosion_control' => $erosionControl, ':nutrient_balance' => $nutrientBalance,
            ':soil_structure' => $soilStructure, ':erosion' => $erosion,
            ':fert' => $fertNote !== '' ? $fertNote : null, ':source' => $source,
            ':source_reference' => $sourceReference !== '' ? $sourceReference : null, ':id' => $id,
        ]);

        header('Location: agro_list.php?msg=' . urlencode('Updated ' . $row['crop_name'] . ' suitability for ' . $row['district_name'] . '.'));
        exit;
    }

    // Re-populate on validation failure.
    $row['soil_score'] = $soil;
    $row['climate_score'] = $climate;
    $row['erosion_control_score'] = $erosionControl;
    $row['nutrient_balance_score'] = $nutrientBalance;
    $row['soil_structure_score'] = $soilStructure;
    $row['erosion_risk'] = $erosion;
    $row['fertilizer_note'] = $fertNote;
    $row['source'] = $source;
    $row['source_reference'] = $sourceReference;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit suitability — <?= h($row['crop_name']) ?> in <?= h($row['district_name']) ?></title>
    <link rel="stylesheet" href="../public/assets/css/style.css">
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body class="admin-body">

<nav class="admin-nav">
    <a href="index.php">Foods</a>
    <a href="agro_list.php">Crop suitability</a>
    <span class="spacer"></span>
    <a href="logout.php">Sign out</a>
</nav>

<main class="wrap">
    <h1><?= h($row['crop_name']) ?> in <?= h($row['district_name']) ?></h1>

    <?php foreach ($errors as $err): ?>
        <p class="alert-error"><?= h($err) ?></p>
    <?php endforeach; ?>

    <form method="post" class="admin-form" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">

        <label for="soil_score">Soil suitability (0-100)</label>
        <input type="number" step="0.01" min="0" max="100" id="soil_score" name="soil_score" required value="<?= h((string) $row['soil_score']) ?>">

        <label for="climate_score">Climate suitability (0-100)</label>
        <input type="number" step="0.01" min="0" max="100" id="climate_score" name="climate_score" required value="<?= h((string) $row['climate_score']) ?>">

        <label for="erosion_control_score">Erosion protection potential (0-100)</label>
        <input type="number" step="0.01" min="0" max="100" id="erosion_control_score" name="erosion_control_score" required value="<?= h((string) $row['erosion_control_score']) ?>">

        <label for="nutrient_balance_score">Nutrient balance / lower depletion potential (0-100)</label>
        <input type="number" step="0.01" min="0" max="100" id="nutrient_balance_score" name="nutrient_balance_score" required value="<?= h((string) $row['nutrient_balance_score']) ?>">

        <label for="soil_structure_score">Soil structure / organic matter potential (0-100)</label>
        <input type="number" step="0.01" min="0" max="100" id="soil_structure_score" name="soil_structure_score" required value="<?= h((string) $row['soil_structure_score']) ?>">
        <p class="form-help">Higher values indicate stronger expected stewardship under suitable management. Assess locally; defaults are illustrative.</p>

        <label for="erosion_risk">Erosion risk</label>
        <select id="erosion_risk" name="erosion_risk">
            <?php foreach (['low', 'medium', 'high'] as $level): ?>
                <option value="<?= $level ?>" <?= $row['erosion_risk'] === $level ? 'selected' : '' ?>><?= ucfirst($level) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="fertilizer_note">Fertilizer note</label>
        <input type="text" id="fertilizer_note" name="fertilizer_note" value="<?= h((string) ($row['fertilizer_note'] ?? '')) ?>">

        <label for="source">Data source</label>
        <select id="source" name="source">
            <option value="illustrative_estimate" <?= $row['source'] === 'illustrative_estimate' ? 'selected' : '' ?>>Illustrative estimate</option>
            <option value="rwasis_import" <?= $row['source'] === 'rwasis_import' ? 'selected' : '' ?>>RwaSIS import</option>
            <option value="soilgrids_isric" <?= $row['source'] === 'soilgrids_isric' ? 'selected' : '' ?>>SoilGrids (ISRIC) fallback</option>
        </select>

        <label for="source_reference">Source citation / dataset details</label>
        <input type="text" id="source_reference" name="source_reference" maxlength="255" value="<?= h((string) ($row['source_reference'] ?? '')) ?>" placeholder="URL, dataset version, depth, and location">
        <p class="form-help">For SoilGrids, record the dataset or export, depth interval, property and statistic used. It provides soil-property maps, not crop-suitability scores; document how the score was derived.</p>
        <p class="form-help"><a href="https://soilgrids.org/" target="_blank" rel="noopener noreferrer">SoilGrids (ISRIC)</a> · <a href="https://docs.isric.org/globaldata/soilgrids/" target="_blank" rel="noopener noreferrer">Documentation</a></p>

        <div class="actions">
            <button type="submit" class="btn">Save</button>
            <a href="agro_list.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</main>
</body>
</html>
