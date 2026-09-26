<?php
session_start();

require_once __DIR__ . DIRECTORY_SEPARATOR . 'nutrition-tool' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';

$pdo = get_db_connection();