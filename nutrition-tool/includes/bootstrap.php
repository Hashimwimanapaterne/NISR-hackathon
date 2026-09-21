<?php
/**
 * Single entry point that every public-facing script requires.
 * Keeps include order consistent and avoids duplicate requires.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0'); // never show raw PHP errors to end users
ini_set('log_errors', '1');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/functions.php';
