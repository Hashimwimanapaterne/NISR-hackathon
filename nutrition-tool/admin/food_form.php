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
$nutrients = [
    'calories_kcal' => '', 'protein_g' => '', 'iron_mg' => '', 'calcium_mg' => '', 'vitamin_a_ug' => '',
];

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
        if ($val < 0) $errors[] = ALLOWED_NUTRIENTS[$col] . ' cannot be negative.';
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

            $stmt = $pdo->prepare('
                INSERT INTO nutrient_profiles (food_id, calories_kcal, protein_g, iron_mg, calcium_mg, vitamin_a_ug, source_note)
                VALUES (:id, :cal, :pro, :iron, :calcium, :vita, :note)
                ON DUPLICATE KEY UPDATE
                    calories_kcal = VALUES(calories_kcal),
                    protein_g     = VALUES(protein_g),
                    iron_mg       = VALUES(iron_mg),
                    calcium_mg    = VALUES(calcium_mg),
                    vitamin_a_ug  = VALUES(vitamin_a_ug)
            ');
            $stmt->execute([
                ':id' => $foodId,
                ':cal' => $nutrientInput['calories_kcal'],
                ':pro' => $nutrientInput['protein_g'],
                ':iron' => $nutrientInput['iron_mg'],
                ':calcium' => $nutrientInput['calcium_mg'],
                ':vita' => $nutrientInput['vitamin_a_ug'],
                ':note' => 'Entered via admin panel',
            ]);

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
    <title><?= $foodId > 0 ? 'Edit food' : 'Add food' ?> — Admin</title>
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
    <h1><?= $foodId > 0 ? 'Edit food' : 'Add a food' ?></h1>

    <?php foreach ($errors as $err): ?>
        <p class="alert-error"><?= h($err) ?></p>
    <?php endforeach; ?>

    <form method="post" class="admin-form" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $food['id'] ?>">

        <label for="name">Food name</label>
        <input type="text" id="name" name="name" required value="<?= h($food['name']) ?>">

        <label for="category">Category</label>
        <input type="text" id="category" name="category" required value="<?= h($food['category']) ?>" placeholder="e.g. Grains, Legumes, Vegetables">

        <label for="unit_label">Price unit label</label>
        <input type="text" id="unit_label" name="unit_label" required value="<?= h($food['unit_label']) ?>" placeholder="kg, litre, piece, tray(30)">

        <label for="grams_per_unit">Grams represented by one unit</label>
        <input type="number" step="0.01" id="grams_per_unit" name="grams_per_unit" required value="<?= h((string) $food['grams_per_unit']) ?>">

        <h2 style="font-family: var(--font-display); font-size: 1.2rem; margin-top: 1.5rem;">Nutrients (per 100g edible portion)</h2>

        <?php foreach (ALLOWED_NUTRIENTS as $col => $label): ?>
            <label for="<?= h($col) ?>"><?= h($label) ?></label>
            <input type="number" step="0.01" min="0" id="<?= h($col) ?>" name="<?= h($col) ?>"
                   value="<?= h((string) ($nutrients[$col] ?? '')) ?>">
        <?php endforeach; ?>

        <div class="actions">
            <button type="submit" class="btn">Save food</button>
            <a href="index.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</main>
</body>
</html>
