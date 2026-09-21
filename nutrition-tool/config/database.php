<?php
/**
 * Database connection (PDO).
 *
 * Credentials are read from environment variables so nothing
 * sensitive is committed to the repo. Copy .env.example to .env
 * (or set real environment variables on your host) before running.
 */

declare(strict_types=1);

function get_db_connection(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $envFile = __DIR__ . '/../.env';
    if (is_readable($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (
                strlen($value) >= 2 &&
                (($value[0] === '"' && $value[-1] === '"') ||
                    ($value[0] === "'" && $value[-1] === "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            if (getenv($key) === false) {
                putenv("$key=$value");
            }
        }
    }

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_NAME') ?: 'umurima_nutrition';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';
    $sslCa = getenv('DB_SSL_CA') ?: __DIR__ . '/../../ca.pem';

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

    try {
        if (!is_readable($sslCa)) {
            throw new RuntimeException('MySQL SSL CA certificate is missing or not readable.');
        }

        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // use real prepared statements
            PDO::MYSQL_ATTR_SSL_CA       => $sslCa,
            // Aiven provides a project CA, but its endpoint certificate may
            // not match the hostname under older mysqlnd builds.
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        ]);
    } catch (PDOException | RuntimeException $e) {
        // Never leak connection details (host/user/pass) to the client.
        error_log('DB connection failed: ' . $e->getMessage());
        http_response_code(500);
        die('Service temporarily unavailable. Please try again later.');
    }
    return $pdo;
}
