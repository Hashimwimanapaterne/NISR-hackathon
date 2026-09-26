<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

start_secure_session();
send_security_headers();
require_admin_login();

$pdo = get_db_connection();

$foodId = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id']) ? (int) $_POST['id'] : 0);
$errors = [];

// Defaults for a blank "add" form.
$food = [
    'id' => 0, 'name' => '', 'category' => '', 'unit_label' => 'kg', 'grams_per_unit' => 1000,
];
$nutrients = array_fill_keys(array_keys(ALLOWED_NUTRIENTS), '');

if ($foodId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM foods WHERE id = :id');
    $stmt->execute([':id' => $foodId]);
    $existing = $stmt->fetch();
    if ($existing === false) {
        http_response_code(404);
        die('Food not found.');
    }
    $food = $existing;

    $stmt = $pdo->prepare('SELECT * FROM nutrient_profiles WHERE food_id = :id');
    $stmt->execute([':id' => $foodId]);
    $existingNutrients = $stmt->fetch();
    if ($existingNutrients !== false) {
        $nutrients = $existingNutrients;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? '');

    $name          = trim((string) ($_POST['name'] ?? ''));
    $category      = trim((string) ($_POST['category'] ?? ''));
    $unitLabel     = trim((string) ($_POST['unit_label'] ?? ''));
    $gramsPerUnit  = (float) ($_POST['grams_per_unit'] ?? 0);

    if ($name === '') $errors[] = 'Name is required.';
    if ($category === '') $errors[] = 'Category is required.';
    if ($unitLabel === '') $errors[] = 'Unit label is required.';
    if ($gramsPerUnit <= 0) $errors[] = 'Grams per unit must be a positive number.';

    $nutrientInput = [];
    foreach (array_keys(ALLOWED_NUTRIENTS) as $col) {
        $val = (float) ($_POST[$col] ?? 0);
        if ($val < 0) $errors[] = ALLOWED_NUTRIENTS[$col]['label'] . ' cannot be negative.';
        $nutrientInput[$col] = $val;
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            if ($foodId > 0) {
                $stmt = $pdo->prepare('
                    UPDATE foods SET name = :name, category = :category,
                           unit_label = :unit, grams_per_unit = :gpu
                    WHERE id = :id
                ');
                $stmt->execute([
                    ':name' => $name, ':category' => $category,
                    ':unit' => $unitLabel, ':gpu' => $gramsPerUnit, ':id' => $foodId,
                ]);
            } else {
                $stmt = $pdo->prepare('
                    INSERT INTO foods (name, category, unit_label, grams_per_unit)
                    VALUES (:name, :category, :unit, :gpu)
                ');
                $stmt->execute([
                    ':name' => $name, ':category' => $category,
                    ':unit' => $unitLabel, ':gpu' => $gramsPerUnit,
                ]);
                $foodId = (int) $pdo->lastInsertId();
            }

            // Built dynamically from ALLOWED_NUTRIENTS so adding a new
            // nutrient later only means adding one line in functions.php —
            // this SQL never needs to change again.
            $nutrientCols = array_keys(ALLOWED_NUTRIENTS);
            $setClause    = implode(', ', array_map(fn($c) => "{$c} = VALUES({$c})", $nutrientCols));
            $insertCols   = implode(', ', $nutrientCols);
            $placeholders = implode(', ', array_map(fn($c) => ":{$c}", $nutrientCols));

            $stmt = $pdo->prepare("
                INSERT INTO nutrient_profiles (food_id, {$insertCols}, source_note)
                VALUES (:food_id, {$placeholders}, :note)
                ON DUPLICATE KEY UPDATE {$setClause}
            ");
            $bindings = [':food_id' => $foodId, ':note' => 'Entered via admin panel'];
            foreach ($nutrientCols as $col) {
                $bindings[":{$col}"] = $nutrientInput[$col];
            }
            $stmt->execute($bindings);

            $pdo->commit();
            header('Location: index.php?msg=' . urlencode('Food saved.'));
            exit;
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('food_form.php save error: ' . $e->getMessage());
            $errors[] = 'Could not save this food. Please try again.';
        }
    }

    // Re-populate the form with submitted values on validation failure.
    $food = ['id' => $foodId, 'name' => $name, 'category' => $category, 'unit_label' => $unitLabel, 'grams_per_unit' => $gramsPerUnit];
    $nutrients = $nutrientInput;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#173d31">
    <title><?= $foodId > 0 ? 'Edit food' : 'Add food' ?> — Umurima Data admin</title>
    <link rel="stylesheet" href="../public/assets/css/style.css">
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body class="admin-body">

<nav class="admin-nav" aria-label="Admin navigation">
    <div class="admin-nav-inner">
        <a class="brand" href="index.php"><span class="brand-mark" aria-hidden="true">U</span><span>umurima<span class="brand-light">data</span></span></a>
        <div class="admin-links">
            <a href="index.php">Foods &amp; prices</a>
            <a href="food_form.php" aria-current="page">Add food</a>
        </div>
        <span class="spacer"></span>
        <span class="admin-identity">Signed in as <?= h($_SESSION['admin_username'] ?? '') ?></span>
        <a class="public-link" href="../public/index.php">View public tool ↗</a>
        <a href="logout.php">Sign out</a>
    </div>
</nav>

<main class="wrap">
    <div class="admin-page-heading">
        <div>
            <p class="section-kicker">Food catalog</p>
            <h1><?= $foodId > 0 ? 'Edit food' : 'Add a food' ?></h1>
            <p>Enter food details and nutrition values per 100g edible portion.</p>
        </div>
    </div>

    <?php foreach ($errors as $err): ?>
        <p class="alert-error" role="alert"><?= h($err) ?></p>
    <?php endforeach; ?>

    <form method="post" class="admin-form">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $food['id'] ?>">

        <section class="form-panel" aria-labelledby="food-details-title">
            <h2 id="food-details-title">Food details</h2>
            <div class="form-grid">
                <div class="form-field">
                    <label for="name">Food name</label>
                    <input type="text" id="name" name="name" required maxlength="120" value="<?= h($food['name']) ?>" placeholder="e.g. Red kidney beans">
                </div>

                <div class="form-field">
                    <label for="category">Category</label>
                    <input type="text" id="category" name="category" required maxlength="60" value="<?= h($food['category']) ?>" placeholder="e.g. Grains, Legumes, Vegetables">
                </div>

                <div class="form-field">
                    <label for="unit_label">Price unit label</label>
                    <input type="text" id="unit_label" name="unit_label" required maxlength="30" value="<?= h($food['unit_label']) ?>" placeholder="kg, litre, piece, tray">
                </div>

                <div class="form-field">
                    <label for="grams_per_unit">Grams in one unit</label>
                    <input type="number" step="0.01" min="0.01" id="grams_per_unit" name="grams_per_unit" required value="<?= h((string) $food['grams_per_unit']) ?>">
                </div>
            </div>
        </section>

        <?php foreach (get_nutrient_groups() as $groupName => $cols): ?>
            <section class="form-panel" aria-labelledby="nutrient-<?= h(str_replace(' ', '-', strtolower($groupName))) ?>">
                <h2 id="nutrient-<?= h(str_replace(' ', '-', strtolower($groupName))) ?>">
                    <?= h($groupName) ?>
                    <span>Nutrition values per 100g edible portion</span>
                </h2>
                <div class="nutrient-grid">
                    <?php foreach ($cols as $col => $meta): ?>
                        <div class="form-field">
                            <label for="<?= h($col) ?>"><?= h($meta['label']) ?> (<?= h($meta['unit']) ?>)</label>
                            <input type="number" step="0.01" min="0" id="<?= h($col) ?>" name="<?= h($col) ?>"
                                   value="<?= h((string) ($nutrients[$col] ?? '')) ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>

        <div class="actions">
            <button type="submit" class="btn"><?= $foodId > 0 ? 'Save changes' : 'Save food' ?></button>
            <a href="index.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</main>
</body>
</html>
