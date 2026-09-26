<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

start_secure_session();
send_security_headers();
require_admin_login();

$pdo = get_db_connection();

$foodId = isset($_GET['food_id']) ? (int) $_GET['food_id'] : (isset($_POST['food_id']) ? (int) $_POST['food_id'] : 0);
$errors = [];

$stmt = $pdo->prepare('SELECT id, name, unit_label FROM foods WHERE id = :id');
$stmt->execute([':id' => $foodId]);
$food = $stmt->fetch();
if ($food === false) {
    http_response_code(404);
    die('Food not found.');
}

$priceInput = '';
$marketInput = '';
$districtInput = '';
$recordedOnInput = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? '');

    $priceInput = trim((string) ($_POST['price_rwf'] ?? ''));
    $marketInput = trim((string) ($_POST['market_name'] ?? ''));
    $districtInput = trim((string) ($_POST['district'] ?? ''));
    $recordedOnInput = (string) ($_POST['recorded_on'] ?? date('Y-m-d'));
    $price = (float) $priceInput;

    if ($price <= 0) $errors[] = 'Price must be a positive number.';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $recordedOnInput)) $errors[] = 'Date is invalid.';

    if (empty($errors)) {
        $stmt = $pdo->prepare('
            INSERT INTO prices (food_id, price_rwf, market_name, district, recorded_on, source)
            VALUES (:food_id, :price, :market, :district, :recorded_on, "manual")
        ');
        $stmt->execute([
            ':food_id' => $foodId,
            ':price' => $price,
            ':market' => $marketInput !== '' ? $marketInput : null,
            ':district' => $districtInput !== '' ? $districtInput : null,
            ':recorded_on' => $recordedOnInput,
        ]);

        header('Location: index.php?msg=' . urlencode('Price recorded for ' . $food['name'] . '.'));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#173d31">
    <title>Add price — <?= h($food['name']) ?> — Umurima Data admin</title>
    <link rel="stylesheet" href="../public/assets/css/style.css">
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body class="admin-body">

<nav class="admin-nav" aria-label="Admin navigation">
    <div class="admin-nav-inner">
        <a class="brand" href="index.php"><span class="brand-mark" aria-hidden="true">U</span><span>umurima<span class="brand-light">data</span></span></a>
        <div class="admin-links">
            <a href="index.php" aria-current="page">Foods &amp; prices</a>
            <a href="food_form.php">Add food</a>
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
            <p class="section-kicker">Market price update</p>
            <h1>Record a price</h1>
            <p><?= h($food['name']) ?> <span aria-hidden="true">·</span> price per <?= h($food['unit_label']) ?></p>
        </div>
    </div>

    <?php foreach ($errors as $err): ?>
        <p class="alert-error" role="alert"><?= h($err) ?></p>
    <?php endforeach; ?>

    <form method="post" class="admin-form">
        <?= csrf_field() ?>
        <input type="hidden" name="food_id" value="<?= (int) $food['id'] ?>">

        <section class="form-panel" aria-labelledby="price-panel-title">
            <h2 id="price-panel-title">Price details</h2>
            <div class="form-grid">
                <div class="form-field">
                    <label for="price_rwf">Price (RWF per <?= h($food['unit_label']) ?>)</label>
                    <input type="number" step="0.01" min="0.01" id="price_rwf" name="price_rwf" required value="<?= h($priceInput) ?>" placeholder="e.g. 1200">
                </div>

                <div class="form-field">
                    <label for="recorded_on">Date recorded</label>
                    <input type="date" id="recorded_on" name="recorded_on" value="<?= h($recordedOnInput) ?>" required>
                </div>

                <div class="form-field">
                    <label for="market_name">Market name <span>(optional)</span></label>
                    <input type="text" id="market_name" name="market_name" maxlength="120" placeholder="e.g. Kimironko" value="<?= h($marketInput) ?>">
                </div>

                <div class="form-field">
                    <label for="district">District <span>(optional)</span></label>
                    <input type="text" id="district" name="district" maxlength="80" placeholder="e.g. Gasabo" value="<?= h($districtInput) ?>">
                </div>
            </div>
        </section>

        <div class="actions">
            <button type="submit" class="btn">Save price</button>
            <a href="index.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</main>
</body>
</html>
