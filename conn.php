<?php
session_start();

// ------------------------------------------------------------------
// 1. Load environment variables from .env (same directory as this file)
// ------------------------------------------------------------------
$envFile = __DIR__ . DIRECTORY_SEPARATOR . '.env';

if (is_file($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        // Skip empty lines and comments
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        // Skip malformed lines
        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);

        // Remove surrounding quotes if present
        if (
            strlen($value) >= 2 &&
            (
                ($value[0] === '"' && $value[-1] === '"') ||
                ($value[0] === "'" && $value[-1] === "'")
            )
        ) {
            $value = substr($value, 1, -1);
        }

        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

// ------------------------------------------------------------------
// 2. Read database credentials
// ------------------------------------------------------------------
$dbHost = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? '127.0.0.1');
$dbPort = getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? '3306');
$dbName = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? '');
$dbUser = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? 'root');
$dbPass = getenv('DB_PASS') ?: ($_ENV['DB_PASS'] ?? '');

// ------------------------------------------------------------------
// 3. Optional SSL configuration
// ------------------------------------------------------------------
// Local XAMPP MySQL normally does not provide an SSL certificate. Set
// DB_SSL_ENABLED=true and provide DB_SSL_CA when the server requires SSL.
$sslEnabled = filter_var(
    getenv('DB_SSL_ENABLED') ?: ($_ENV['DB_SSL_ENABLED'] ?? 'false'),
    FILTER_VALIDATE_BOOLEAN
);
$sslCa = getenv('DB_SSL_CA') ?: ($_ENV['DB_SSL_CA'] ?? '');

// ------------------------------------------------------------------
// 4. Connect to MySQL
// ------------------------------------------------------------------
try {
    $dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    if ($sslEnabled) {
        if ($sslCa === '' || !is_readable($sslCa)) {
            die("SSL CA certificate not found or not readable at: $sslCa");
        }

        $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
    }

    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
    // Connection successful. Uncomment the next line only for debugging.
    // echo "Connected successfully!";
} catch (PDOException $e) {
    // Do not expose credentials or detailed errors in production.
    die("Database connection failed: " . $e->getMessage());
}
?>