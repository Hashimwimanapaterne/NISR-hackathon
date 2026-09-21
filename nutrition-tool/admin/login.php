<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

start_secure_session();
send_security_headers();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? '');

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $pdo = get_db_connection();
    $stmt = $pdo->prepare('SELECT id, password_hash FROM admin_users WHERE username = :u LIMIT 1');
    $stmt->execute([':u' => $username]);
    $admin = $stmt->fetch();

    // Always run password_verify, even on a missing user, against a
    // dummy hash — this keeps response timing similar either way and
    // avoids leaking which usernames exist via a timing side-channel.
    $hashToCheck = $admin['password_hash'] ?? '$2y$10$invalidinvalidinvaliduinvalidinvalidinvalidinvalidinva';
    $valid = password_verify($password, $hashToCheck) && $admin !== false;

    if ($valid) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $username;
        header('Location: index.php');
        exit;
    }

    $error = 'Invalid username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin login — Umurima Data</title>
    <link rel="stylesheet" href="../public/assets/css/style.css">
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body class="admin-body">
<main class="admin-auth-wrap">
    <h1>Admin sign in</h1>
    <?php if ($error !== ''): ?>
        <p class="alert-error"><?= h($error) ?></p>
    <?php endif; ?>
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required autofocus autocomplete="username">

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">

        <button type="submit">Sign in</button>
    </form>
</main>
</body>
</html>
