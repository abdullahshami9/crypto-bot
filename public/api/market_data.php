<?php
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 1);
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

// Helper to fetch from Binance
function fetchBinanceCandles($symbol, $limit=100) {
    $url = "https://api.binance.com/api/v3/klines?symbol=$symbol&interval=1h&limit=$limit";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local dev
    $output = curl_exec($ch);
    curl_close($ch);
    return json_decode($output, true);
}

// Fetch Historical Data from DB
$stmt = $pdo->prepare("SELECT close_time, open, high, low, close, volume FROM historical_candles WHERE symbol = ? ORDER BY close_time ASC LIMIT ?");
$stmt->execute([$symbol, $limit]);
$candles = $stmt->fetchAll();

// Check if data is stale (older than 1 hour) or empty
$is_stale = false;
if (empty($candles)) {
    $is_stale = true;
} else {
    $last_candle_time = strtotime(end($candles)['close_time']);
    if (time() - $last_candle_time > 3600) {
        $is_stale = true;
    }
}

if ($is_stale) {
    // Fetch fresh data from Binance
    $binance_data = fetchBinanceCandles($symbol, $limit);
    
    if ($binance_data && is_array($binance_data)) {
        // Update DB using REPLACE INTO to handle duplicates gracefully
        $insert_stmt = $pdo->prepare("
            REPLACE INTO historical_candles (symbol, `interval`, open, high, low, close, volume, close_time)
            VALUES (?, '1h', ?, ?, ?, ?, ?, ?)
        ");
        
        $candles = []; // Reset to fill with new data
        
        foreach ($binance_data as $kline) {
            // Binance: [time, open, high, low, close, volume, close_time, ...]
            $close_time_ts = $kline[6] / 1000;
            $close_time_str = date('Y-m-d H:i:s', $close_time_ts);
            
            $insert_stmt->execute([
                $symbol,
                $kline[1], $kline[2], $kline[3], $kline[4], $kline[5],
                $close_time_str
            ]);
            
            $candles[] = [
                'close_time' => $close_time_str,
                'open' => $kline[1],
                'high' => $kline[2],
                'low' => $kline[3],
                'close' => $kline[4],
                'volume' => $kline[5]
            ];
        }
    }
}

// Fetch Latest Prediction (Future)
$stmt_pred = $pdo->prepare("SELECT prediction_time, predicted_open as open, predicted_high as high, predicted_low as low, predicted_close as close, confidence_score FROM predictions WHERE symbol = ? AND prediction_time > NOW() ORDER BY created_at DESC LIMIT 1");
$stmt_pred->execute([$symbol]);
$prediction = $stmt_pred->fetch();

// Fetch Past Predictions for Validation (Last 20)
$stmt_past = $pdo->prepare("SELECT prediction_time, predicted_open, predicted_close FROM predictions WHERE symbol = ? AND prediction_time <= NOW() ORDER BY prediction_time DESC LIMIT 20");
$stmt_past->execute([$symbol]);
$past_predictions = $stmt_past->fetchAll();

$validated_predictions = [];
foreach ($past_predictions as $past) {
    $pred_time = strtotime($past['prediction_time']);
    // Find actual candle at this time
    // We can use the $candles array we already fetched.
    // Candles are sorted by close_time ASC.
    // We need to find a candle where close_time is close to prediction_time.
    
    $match = null;
    foreach ($candles as $candle) {
        $candle_time = strtotime($candle['close_time']);
        // Assuming prediction_time aligns with candle close time roughly
        if (abs($candle_time - $pred_time) < 300) { // Within 5 minutes
            $match = $candle;
            break;
        }
    }
    
    if ($match) {
        $predicted_dir = $past['predicted_close'] > $past['predicted_open'] ? 1 : -1;
        $actual_dir = $match['close'] > $match['open'] ? 1 : -1;
        
        $is_correct = ($predicted_dir === $actual_dir);
        
        $validated_predictions[] = [
            'time' => $pred_time,
            'is_correct' => $is_correct
        ];
    }
}

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
    'prediction' => $formatted_prediction,
    'past_predictions' => $validated_predictions
]);
