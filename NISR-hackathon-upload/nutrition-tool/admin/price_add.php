<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

start_secure_session();
send_security_headers();
require_admin_login();

$pdo = get_db_connection();

$foodId = isset($_GET['food_id']) ? (int) $_GET['food_id'] : (isset($_POST['food_id']) ? (int) $_POST['food_id'] : 0);
$priceId = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id']) ? (int) $_POST['id'] : 0);
$errors = [];

$stmt = $pdo->prepare('SELECT id, name, unit_label FROM foods WHERE id = :id');
$stmt->execute([':id' => $foodId]);
$food = $stmt->fetch();
if ($food === false) {
    http_response_code(404);
    die('Food not found.');
}
$existingPrice = null;
if ($priceId > 0) {
    $priceStatement = $pdo->prepare('SELECT * FROM prices WHERE id = :id AND food_id = :food_id');
    $priceStatement->execute([':id' => $priceId, ':food_id' => $foodId]);
    $existingPrice = $priceStatement->fetch();
    if ($existingPrice === false) {
        http_response_code(404);
        die('Price record not found for this food.');
    }
}
$districts = $pdo->query('SELECT name FROM districts ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);

$source = (string) ($existingPrice['source'] ?? 'manual_observation');
$sourceReference = (string) ($existingPrice['source_reference'] ?? '');
$market = (string) ($existingPrice['market_name'] ?? '');
$district = (string) ($existingPrice['district'] ?? '');
$priceInput = $existingPrice !== null ? (string) $existingPrice['price_rwf'] : '';
$recordedOn = (string) ($existingPrice['recorded_on'] ?? date('Y-m-d'));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? '');

    $priceInput = trim((string) ($_POST['price_rwf'] ?? ''));
    $price      = is_numeric($priceInput) ? (float) $priceInput : NAN;
    $market     = trim((string) ($_POST['market_name'] ?? ''));
    $district   = trim((string) ($_POST['district'] ?? ''));
    $recordedOn = (string) ($_POST['recorded_on'] ?? '');
    if ($existingPrice === null) {
        $source = (string) ($_POST['source'] ?? 'manual_observation');
        $sourceReference = trim((string) ($_POST['source_reference'] ?? ''));
    }

    if (!is_finite($price) || $price <= 0 || $price > 99999999.99) $errors[] = 'Price must be a positive number no greater than 99,999,999.99 RWF.';
    if ($market === '' || strlen($market) > 120) $errors[] = 'Enter a market name of 1 to 120 characters.';
    if (!in_array($district, $districts, true)) $errors[] = 'Select a district from the Rwanda district list.';
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $recordedOn);
    if ($date === false || $date->format('Y-m-d') !== $recordedOn) {
        $errors[] = 'Date is invalid.';
    } elseif ($date > new DateTimeImmutable('today')) {
        $errors[] = 'Price date cannot be in the future.';
    }
    if ($existingPrice === null) {
        if (!in_array($source, ['manual_observation', 'esoko_import', 'nisr_dataset', 'other_public_dataset'], true)) {
            $errors[] = 'Select a valid price source.';
        }
        if (strlen($sourceReference) > 500) $errors[] = 'Source details must be 500 characters or fewer.';
        if ($source !== 'manual_observation' && $sourceReference === '') {
            $errors[] = 'Imported prices require a dataset citation, version/date, and URL or source identifier.';
        }
    }

    if (empty($errors)) {
        try {
            if ($priceId > 0) {
                $stmt = $pdo->prepare('
                    UPDATE prices
                    SET price_rwf = :price, market_name = :market,
                        district = :district, recorded_on = :recorded_on
                    WHERE id = :id AND food_id = :food_id
                ');
                $stmt->execute([
                    ':price' => $price,
                    ':market' => $market,
                    ':district' => $district,
                    ':recorded_on' => $recordedOn,
                    ':id' => $priceId,
                    ':food_id' => $foodId,
                ]);
                if ($stmt->rowCount() === 0) {
                    $checkStatement = $pdo->prepare('SELECT 1 FROM prices WHERE id = :id AND food_id = :food_id');
                    $checkStatement->execute([':id' => $priceId, ':food_id' => $foodId]);
                    if ($checkStatement->fetchColumn() === false) {
                        http_response_code(404);
                        die('Price record not found for this food.');
                    }
                }
                $message = 'Price updated for ' . $food['name'] . '.';
            } else {
                $stmt = $pdo->prepare('
                    INSERT INTO prices (food_id, price_rwf, market_name, district, recorded_on, source, source_reference)
                    VALUES (:food_id, :price, :market, :district, :recorded_on, :source, :source_reference)
                ');
                $stmt->execute([
                    ':food_id' => $foodId,
                    ':price' => $price,
                    ':market' => $market,
                    ':district' => $district,
                    ':recorded_on' => $recordedOn,
                    ':source' => $source,
                    ':source_reference' => $sourceReference !== '' ? $sourceReference : null,
                ]);
                $message = 'Price recorded for ' . $food['name'] . '.';
            }

            header('Location: index.php?msg=' . urlencode($message));
            exit;
        } catch (Throwable $error) {
            error_log('price_add.php save error: ' . $error->getMessage());
            $errors[] = 'Could not save this price. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $priceId > 0 ? 'Edit price' : 'Add price' ?> — <?= h($food['name']) ?></title>
    <link rel="stylesheet" href="../public/assets/css/style.css">
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body class="admin-body">

<nav class="admin-nav">
    <a href="index.php">Foods</a>
    <a href="food_form.php">Add food</a>
    <span class="spacer"></span>
    <a href="logout.php">Sign out</a>
</nav>

<main class="wrap">
    <h1><?= $priceId > 0 ? 'Edit price' : 'Record a price' ?> — <?= h($food['name']) ?></h1>

    <?php foreach ($errors as $err): ?>
        <p class="alert-error"><?= h($err) ?></p>
    <?php endforeach; ?>

    <form method="post" class="admin-form" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="food_id" value="<?= (int) $food['id'] ?>">
        <input type="hidden" name="id" value="<?= (int) $priceId ?>">

        <?php if ($priceId > 0): ?>
            <p class="form-help">Editing preserves the original source type and citation. Update only the recorded price, market, district, or date; if the imported source data are wrong, add a corrected record with its verified provenance instead.</p>
        <?php endif; ?>
        <label for="price_rwf">Price (RWF per <?= h($food['unit_label']) ?>)</label>
        <input type="number" step="0.01" min="0" id="price_rwf" name="price_rwf" required value="<?= h($priceInput) ?>">

        <label for="market_name">Market name</label>
        <input type="text" id="market_name" name="market_name" maxlength="120" required value="<?= h($market) ?>" placeholder="e.g. Kimironko">

        <label for="district">District</label>
        <select id="district" name="district" required>
            <option value="">Choose district…</option>
            <?php foreach ($districts as $districtName): ?>
                <option value="<?= h($districtName) ?>" <?= $district === $districtName ? 'selected' : '' ?>><?= h($districtName) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="recorded_on">Observation date</label>
        <input type="date" id="recorded_on" name="recorded_on" value="<?= h($recordedOn) ?>" required>

        <?php if ($priceId > 0): ?>
            <label>Price data source</label>
            <p class="form-help"><?= h($source) ?><?= $sourceReference !== '' ? ' · ' . h($sourceReference) : '' ?></p>
        <?php else: ?>
            <label for="source">Price data source</label>
            <select id="source" name="source" required>
                <option value="manual_observation" <?= $source === 'manual_observation' ? 'selected' : '' ?>>Direct market observation</option>
                <option value="esoko_import" <?= $source === 'esoko_import' ? 'selected' : '' ?>>e-Soko dataset</option>
                <option value="nisr_dataset" <?= $source === 'nisr_dataset' ? 'selected' : '' ?>>NISR dataset</option>
                <option value="other_public_dataset" <?= $source === 'other_public_dataset' ? 'selected' : '' ?>>Other public dataset</option>
            </select>

            <label for="source_reference">Citation / source reference</label>
            <input type="text" id="source_reference" name="source_reference" maxlength="500" value="<?= h($sourceReference) ?>"
                   placeholder="Dataset title, publisher, release date/version, URL or record ID">
            <p class="form-help">Required for imported data. Record enough detail to verify the source and geographic/market coverage. Never label sample values as observations.</p>
        <?php endif; ?>

        <div class="actions">
            <button type="submit" class="btn"><?= $priceId > 0 ? 'Update price' : 'Save price' ?></button>
            <a href="index.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</main>
</body>
</html>
