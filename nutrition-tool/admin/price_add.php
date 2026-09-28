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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? '');

    $price      = (float) ($_POST['price_rwf'] ?? 0);
    $market     = trim((string) ($_POST['market_name'] ?? ''));
    $district   = trim((string) ($_POST['district'] ?? ''));
    $recordedOn = (string) ($_POST['recorded_on'] ?? date('Y-m-d'));

    if ($price <= 0) $errors[] = 'Price must be a positive number.';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $recordedOn)) $errors[] = 'Date is invalid.';

    if (empty($errors)) {
        $stmt = $pdo->prepare('
            INSERT INTO prices (food_id, price_rwf, market_name, district, recorded_on, source)
            VALUES (:food_id, :price, :market, :district, :recorded_on, :source)
        ');
        $stmt->execute([
            ':food_id' => $foodId,
            ':price' => $price,
            ':market' => $market !== '' ? $market : null,
            ':district' => $district !== '' ? $district : null,
            ':recorded_on' => $recordedOn,
            ':source' => 'manual',
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
    <title>Add price — <?= h($food['name']) ?></title>
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
    <h1>Record a price — <?= h($food['name']) ?></h1>

    <?php foreach ($errors as $err): ?>
        <p class="alert-error"><?= h($err) ?></p>
    <?php endforeach; ?>

    <form method="post" class="admin-form" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="food_id" value="<?= (int) $food['id'] ?>">

        <label for="price_rwf">Price (RWF per <?= h($food['unit_label']) ?>)</label>
        <input type="number" step="0.01" min="0" id="price_rwf" name="price_rwf" required>

        <label for="market_name">Market name (optional)</label>
        <input type="text" id="market_name" name="market_name" placeholder="e.g. Kimironko">

        <label for="district">District (optional)</label>
        <input type="text" id="district" name="district" placeholder="e.g. Gasabo">

        <label for="recorded_on">Date recorded</label>
        <input type="date" id="recorded_on" name="recorded_on" value="<?= h(date('Y-m-d')) ?>" required>

        <div class="actions">
            <button type="submit" class="btn">Save price</button>
            <a href="index.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</main>
</body>
</html>
