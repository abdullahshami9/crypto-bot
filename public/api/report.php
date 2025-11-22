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

// Calculate Daily Stats
// We need to join predictions with historical_candles to check accuracy
// This is complex in SQL alone because timestamps might not match exactly.
// For MVP, we'll fetch today's predictions and validate in PHP.

$today = date('Y-m-d 00:00:00');
$stmt = $pdo->prepare("
    SELECT p.symbol, p.prediction_time, p.predicted_open, p.predicted_close, h.open as actual_open, h.close as actual_close 
    FROM predictions p
    JOIN historical_candles h ON p.symbol = h.symbol 
    WHERE p.created_at >= ? 
    AND ABS(TIMESTAMPDIFF(SECOND, p.prediction_time, h.close_time)) < 300
");
$stmt->execute([$today]);
$results = $stmt->fetchAll();

$total = count($results);
$correct = 0;

foreach ($results as $row) {
    $pred_dir = $row['predicted_close'] > $row['predicted_open'] ? 1 : -1;
    $actual_dir = $row['actual_close'] > $row['actual_open'] ? 1 : -1;
    
    if ($pred_dir === $actual_dir) {
        $correct++;
    }
}

$win_rate = $total > 0 ? round(($correct / $total) * 100, 2) : 0;

echo json_encode([
    'date' => date('Y-m-d'),
    'total_predictions' => $total,
    'correct_predictions' => $correct,
    'win_rate' => $win_rate,
    'status' => $win_rate > 50 ? 'PROFITABLE' : 'LEARNING'
]);
