<?php
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);
if (file_exists('../../includes/db.php')) {
    require_once '../../includes/db.php';
}

// Simple DB Connection if includes/db.php doesn't exist or is complex
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
    // Only connect if $pdo is not already set by the include
    if (!isset($pdo)) {
        $pdo = new PDO($dsn, $user, $pass, $options);
    }
} catch (\PDOException $e) {
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

$symbol = isset($_GET['symbol']) ? $_GET['symbol'] : 'BTCUSDT';
$limit = isset($_GET['limit']) ? $_GET['limit'] : 100;

// Fetch Historical Data
$stmt = $pdo->prepare("SELECT close_time, open, high, low, close, volume FROM historical_candles WHERE symbol = ? ORDER BY close_time ASC LIMIT ?");
$stmt->execute([$symbol, $limit]);
$candles = $stmt->fetchAll();

// Fetch Latest Prediction
$stmt_pred = $pdo->prepare("SELECT prediction_time, predicted_open as open, predicted_high as high, predicted_low as low, predicted_close as close, confidence_score FROM predictions WHERE symbol = ? ORDER BY created_at DESC LIMIT 1");
$stmt_pred->execute([$symbol]);
$prediction = $stmt_pred->fetch();

// Format data for chart (TradingView Lightweight Charts expects seconds timestamp)
$formatted_candles = [];
foreach ($candles as $candle) {
    $formatted_candles[] = [
        'time' => strtotime($candle['close_time']), // Unix timestamp
        'open' => (float)$candle['open'],
        'high' => (float)$candle['high'],
        'low' => (float)$candle['low'],
        'close' => (float)$candle['close'],
        'volume' => (float)$candle['volume']
    ];
}

$formatted_prediction = null;
if ($prediction) {
    // Prediction time is future, so we ensure it's after the last candle
    $last_candle_time = end($formatted_candles)['time'];
    $pred_time = strtotime($prediction['prediction_time']);
    
    // If prediction time is not strictly after last candle, force it to be +15 mins (for demo purposes)
    if ($pred_time <= $last_candle_time) {
        $pred_time = $last_candle_time + (15 * 60);
    }

    $formatted_prediction = [
        'time' => $pred_time,
        'open' => (float)$prediction['open'],
        'high' => (float)$prediction['high'],
        'low' => (float)$prediction['low'],
        'close' => (float)$prediction['close'],
        'confidence' => (float)$prediction['confidence_score']
    ];
}

echo json_encode([
    'symbol' => $symbol,
    'candles' => $formatted_candles,
    'prediction' => $formatted_prediction
]);
