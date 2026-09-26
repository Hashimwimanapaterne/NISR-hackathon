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
$activeFoods = count(array_filter($foods, static fn(array $food): bool => (bool) $food['is_active']));
$foodsWithoutPrice = count(array_filter($foods, static fn(array $food): bool => $food['is_active'] && $food['price_rwf'] === null));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#173d31">
    <title>Foods &amp; prices — Umurima Data admin</title>
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
            <p class="section-kicker">Market data workspace</p>
            <h1>Foods &amp; prices</h1>
            <p>Keep food records and market prices fresh for the public nutrition finder.</p>
        </div>
        <a href="food_form.php" class="btn">＋ Add a food</a>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <p class="alert-success"><?= h((string) $_GET['msg']) ?></p>
    <?php endif; ?>

    <section class="admin-summary" aria-label="Food data summary">
        <div class="summary-item"><span>Food records</span><strong><?= count($foods) ?></strong></div>
        <div class="summary-item"><span>Active foods</span><strong><?= $activeFoods ?></strong></div>
        <div class="summary-item"><span>Need a price</span><strong><?= $foodsWithoutPrice ?></strong></div>
    </section>

    <div class="admin-table-shell">
        <table class="admin-table">
            <thead>
                <tr>
                    <th scope="col">Food</th>
                    <th scope="col">Category</th>
                    <th scope="col">Latest price</th>
                    <th scope="col">Recorded</th>
                    <th scope="col">Status</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($foods as $f): ?>
                <tr>
                    <td><?= h($f['name']) ?></td>
                    <td><?= h($f['category']) ?></td>
                    <td><?= $f['price_rwf'] !== null ? number_format((float) $f['price_rwf']) . ' RWF / ' . h($f['unit_label']) : '—' ?></td>
                    <td><?= h($f['recorded_on'] ?? '—') ?></td>
                    <td><span class="status-badge<?= $f['is_active'] ? '' : ' is-hidden' ?>"><?= $f['is_active'] ? 'Active' : 'Hidden' ?></span></td>
                    <td>
                        <a href="food_form.php?id=<?= (int) $f['id'] ?>">Edit</a>
                        <span aria-hidden="true"> · </span>
                        <a href="price_add.php?food_id=<?= (int) $f['id'] ?>">Add price</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($foods)): ?>
                <tr><td colspan="6" class="empty-table">No foods added yet. <a href="food_form.php">Add your first food</a>.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>
