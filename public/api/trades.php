<?php
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

if (file_exists('../../includes/db.php')) {
    require_once '../../includes/db.php';
}

$host = 'localhost';
$db   = 'crypto_engine';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    if (!isset($pdo)) {
        $pdo = new PDO($dsn, $user, $pass, $options);
    }
} catch (\PDOException $e) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Fetch recent trades
$stmt = $pdo->query("SELECT * FROM trades ORDER BY entry_time DESC LIMIT 20");
$trades = $stmt->fetchAll();

echo json_encode($trades);
