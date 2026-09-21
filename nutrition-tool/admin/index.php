<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

start_secure_session();
send_security_headers();
require_admin_login();

$pdo = get_db_connection();
$foods = $pdo->query("
    SELECT f.id, f.name, f.category, f.unit_label, f.is_active,
           lp.price_rwf, lp.recorded_on
    FROM foods f
    LEFT JOIN latest_prices lp ON lp.food_id = f.id
    ORDER BY f.category, f.name
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin — Foods</title>
    <link rel="stylesheet" href="../public/assets/css/style.css">
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body class="admin-body">

<nav class="admin-nav">
    <a href="index.php">Foods</a>
    <a href="food_form.php">Add food</a>
    <span class="spacer"></span>
    <span>Signed in as <?= h($_SESSION['admin_username'] ?? '') ?></span>
    <a href="logout.php">Sign out</a>
</nav>

<main class="wrap">
    <h1>Foods &amp; prices</h1>
    <p class="result-note">Add a price entry whenever you have a fresh market figure. The public tool always ranks using each food's most recent price.</p>

    <?php if (isset($_GET['msg'])): ?>
        <p class="alert-success"><?= h((string) $_GET['msg']) ?></p>
    <?php endif; ?>

    <table class="admin-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Category</th>
                <th>Latest price</th>
                <th>Recorded</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($foods as $f): ?>
            <tr>
                <td><?= h($f['name']) ?></td>
                <td><?= h($f['category']) ?></td>
                <td><?= $f['price_rwf'] !== null ? number_format((float) $f['price_rwf']) . ' RWF/' . h($f['unit_label']) : '—' ?></td>
                <td><?= h($f['recorded_on'] ?? '—') ?></td>
                <td><?= $f['is_active'] ? 'Active' : 'Hidden' ?></td>
                <td>
                    <a href="food_form.php?id=<?= (int) $f['id'] ?>">Edit</a> ·
                    <a href="price_add.php?food_id=<?= (int) $f['id'] ?>">Add price</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($foods)): ?>
            <tr><td colspan="6">No foods yet. <a href="food_form.php">Add the first one</a>.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</main>
</body>
</html>
