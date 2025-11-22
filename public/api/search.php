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

$query = $_GET['q'] ?? '';
if (strlen($query) < 1) {
    echo json_encode([]);
    exit;
}

// Search for symbols starting with the query
$stmt = $pdo->prepare("SELECT symbol FROM coins WHERE symbol LIKE ? LIMIT 10");
$stmt->execute(["%$query%"]);
$results = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo json_encode($results);
