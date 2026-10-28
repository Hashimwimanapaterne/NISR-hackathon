<?php
declare(strict_types=1);

/**
 * Usage: php bin/create_admin.php <username> <password>
 *
 * Creates (or updates the password of) an admin account. Run this
 * from the command line only — never expose account creation over
 * HTTP without its own separate authorization check.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once __DIR__ . '/../config/database.php';

if ($argc !== 3) {
    fwrite(STDERR, "Usage: php bin/create_admin.php Paterne 2005\n");
    exit(1);
}

[$script, $username, $password] = $argv;

if (strlen($password) < 10) {
    fwrite(STDERR, "Password must be at least 10 characters.\n");
    exit(1);
}

$pdo = get_db_connection();
$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare('
    INSERT INTO admin_users (username, password_hash)
    VALUES (:u, :h)
    ON DUPLICATE KEY UPDATE password_hash = :h2
');
$stmt->execute([':u' => $username, ':h' => $hash, ':h2' => $hash]);

echo "Admin account ready for username: {$username}\n";
