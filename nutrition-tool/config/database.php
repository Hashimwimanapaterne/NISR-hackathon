<?php
/**
 * Database connection (PDO).
 *
 * Credentials are read from environment variables. On hosts where you
 * can't set real environment variables (e.g. shared hosting), the root
 * and nutrition-tool .env files are loaded as fallbacks.
 *
 * This is the ONLY database connection function in the app; every
 * page goes through get_db_connection() via includes/bootstrap.php.
 * Keeping a single connection path — rather than a second, slightly
 * different copy elsewhere — means there's one place to update
 * credentials, one place to patch, and no risk of the two drifting
 * into inconsistent security settings.
 */

declare(strict_types=1);

/** Load KEY=VALUE pairs from a .env file without overriding existing values. */
function load_env_file(string $path): void
{
    if (!is_readable($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if (
            strlen($value) >= 2 &&
            (($value[0] === '"' && $value[-1] === '"') || ($value[0] === "'" && $value[-1] === "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        // Never override a variable the host environment already set.
        if (getenv($key) === false) {
            putenv("{$key}={$value}");
        }
    }
}

function get_db_connection(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $projectRoot = dirname(__DIR__, 2);
    load_env_file($projectRoot . DIRECTORY_SEPARATOR . '.env');
    load_env_file(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '.env');

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_NAME') ?: 'umurima_nutrition';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';

    // SSL is opt-in (off by default) so local development against a
    // plain MySQL/MariaDB instance works with zero extra setup. Turn
    // it on for hosts that require it (e.g. Aiven, PlanetScale) by
    // setting DB_SSL_ENABLED=true and DB_SSL_CA to a CA bundle path.
    $sslEnabled = filter_var(getenv('DB_SSL_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN);
    $sslCa = getenv('DB_SSL_CA') ?: '';
    // Some managed providers (e.g. Aiven) issue a valid project CA whose
    // endpoint certificate doesn't match the hostname under older mysqlnd
    // builds — DB_SSL_VERIFY_CERT lets you relax verification for those
    // specific cases without disabling SSL itself. Leave this at the
    // default (true) unless you hit that exact error.
    $sslVerify = filter_var(getenv('DB_SSL_VERIFY_CERT') ?: 'true', FILTER_VALIDATE_BOOLEAN);

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false, // use real prepared statements
    ];

    try {
        if ($sslEnabled) {
            if ($sslCa === '' || !is_readable($sslCa)) {
                throw new RuntimeException("DB_SSL_ENABLED is true but DB_SSL_CA ('{$sslCa}') is missing or not readable.");
            }
            $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = $sslVerify;
        }

        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (PDOException|RuntimeException $e) {
        // Never leak connection details (host/user/pass/paths) to the client.
        error_log('DB connection failed: ' . $e->getMessage());
        http_response_code(500);
        die('Service temporarily unavailable. Please try again later.');
    }

    return $pdo;
}
