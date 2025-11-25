<?php
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 1);

// Database Connection
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
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

$symbol = isset($_GET['symbol']) ? $_GET['symbol'] : 'BTCUSDT';
$interval = isset($_GET['interval']) ? $_GET['interval'] : '1h';
$limit = isset($_GET['limit']) ? $_GET['limit'] : 100;

// Helper to fetch from Binance
function fetchBinanceCandles($symbol, $interval, $limit=100) {
    $url = "https://api.binance.com/api/v3/klines?symbol=$symbol&interval=$interval&limit=$limit";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $output = curl_exec($ch);
    
    if(curl_errno($ch)){
        return null;
    }
    
    curl_close($ch);
    return json_decode($output, true);
}

// Fetch Historical Data from DB
$stmt = $pdo->prepare("SELECT close_time, open, high, low, close, volume FROM historical_candles WHERE symbol = ? AND `interval` = ? ORDER BY close_time ASC LIMIT ?");
$stmt->execute([$symbol, $interval, $limit]);
$candles = $stmt->fetchAll();

// Determine Interval in Seconds
$interval_seconds = 3600; // Default 1h
if ($interval == '15m') $interval_seconds = 900;
if ($interval == '4h') $interval_seconds = 14400;
if ($interval == '1d') $interval_seconds = 86400;
if ($interval == '1w') $interval_seconds = 604800;
if ($interval == '1M') $interval_seconds = 2592000; // Approx 30 days

// Check if data is stale or empty
$is_stale = false;
if (empty($candles)) {
    $is_stale = true;
} else {
    $last_candle_time = strtotime(end($candles)['close_time']);
    if (time() - $last_candle_time > $interval_seconds) {
        $is_stale = true;
    }
}

if ($is_stale) {
    // Fetch fresh data from Binance
    $binance_data = fetchBinanceCandles($symbol, $interval, $limit);
    
    if ($binance_data && is_array($binance_data) && !isset($binance_data['code'])) {
        // Update DB
        $insert_stmt = $pdo->prepare("
            REPLACE INTO historical_candles (symbol, `interval`, open, high, low, close, volume, close_time)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $candles = []; 
        
        foreach ($binance_data as $kline) {
            $close_time_ts = $kline[6] / 1000;
            $close_time_str = date('Y-m-d H:i:s', $close_time_ts);
            
            $insert_stmt->execute([
                $symbol,
                $interval,
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
    } else if (empty($candles)) {
        // Fallback: Generate Mock Data
        $current_price = 96000;
        $now = time();
        // Round down to nearest interval
        $now = floor($now / $interval_seconds) * $interval_seconds;
        
        for ($i = 0; $i < $limit; $i++) {
            $time = $now - (($limit - 1 - $i) * $interval_seconds);
            $open = $current_price;
            $volatility = 0.005; // 0.5%
            $change = (rand(-100, 100) / 10000) * $volatility; 
            $close = $open * (1 + $change);
            $high = max($open, $close) * (1 + (rand(0, 50) / 10000) * $volatility);
            $low = min($open, $close) * (1 - (rand(0, 50) / 10000) * $volatility);
            
            $candles[] = [
                'close_time' => date('Y-m-d H:i:s', $time),
                'open' => $open,
                'high' => $high,
                'low' => $low,
                'close' => $close,
                'volume' => rand(10, 100)
            ];
            $current_price = $close;
        }
    }
}

// Fetch Latest Prediction (Future)
// Fetch Latest Prediction (Future)
$stmt_pred = $pdo->prepare("SELECT prediction_time, predicted_open as open, predicted_high as high, predicted_low as low, predicted_close as close, confidence_score FROM predictions WHERE symbol = ? AND `interval` = ? AND prediction_time > NOW() ORDER BY created_at DESC LIMIT 1");
$stmt_pred->execute([$symbol, $interval]);
$prediction = $stmt_pred->fetch();

// Mock Prediction if missing
if (!$prediction && !empty($candles)) {
    $last_candle = end($candles);
    $last_close = (float)$last_candle['close'];
    $last_open = (float)$last_candle['open'];
    
    // Simple mock logic
    $is_bullish = $last_close > $last_open;
    $direction = $is_bullish ? 1 : -1;
    
    $pred_open = $last_close;
    $pred_close = $pred_open * (1 + ($direction * (rand(10, 50) / 10000)));
    $pred_high = max($pred_open, $pred_close) * (1 + (rand(0, 20) / 10000));
    $pred_low = min($pred_open, $pred_close) * (1 - (rand(0, 20) / 10000));
    
    $prediction = [
        'prediction_time' => date('Y-m-d H:i:s', strtotime($last_candle['close_time']) + $interval_seconds),
        'open' => $pred_open,
        'high' => $pred_high,
        'low' => $pred_low,
        'close' => $pred_close,
        'confidence_score' => rand(75, 95)
    ];
}

// Fetch Past Predictions
$stmt_past = $pdo->prepare("SELECT prediction_time, predicted_open, predicted_close FROM predictions WHERE symbol = ? AND prediction_time <= NOW() ORDER BY prediction_time DESC LIMIT 20");
$stmt_past->execute([$symbol]);
$past_predictions = $stmt_past->fetchAll();

$validated_predictions = [];
$seen_timestamps = [];

if ($past_predictions) {
    foreach ($past_predictions as $past) {
        $pred_time = strtotime($past['prediction_time']);
        $match = null;
        foreach ($candles as $candle) {
            $candle_time = strtotime($candle['close_time']);
            if (abs($candle_time - $pred_time) < ($interval_seconds / 2)) {
                $match = $candle;
                break;
            }
        }
        
        if ($match) {
            $match_time = strtotime($match['close_time']);
            
            // Deduplicate: Only add if we haven't seen this candle timestamp yet
            if (!isset($seen_timestamps[$match_time])) {
                $predicted_dir = $past['predicted_close'] > $past['predicted_open'] ? 1 : -1;
                $actual_dir = $match['close'] > $match['open'] ? 1 : -1;
                $is_correct = ($predicted_dir === $actual_dir);
                
                $validated_predictions[] = [
                    'time' => $match_time,
                    'is_correct' => $is_correct
                ];
                $seen_timestamps[$match_time] = true;
            }
        }
    }
}

// Format data for chart
$formatted_candles = [];
foreach ($candles as $candle) {
    $formatted_candles[] = [
        'time' => strtotime($candle['close_time']),
        'open' => (float)$candle['open'],
        'high' => (float)$candle['high'],
        'low' => (float)$candle['low'],
        'close' => (float)$candle['close'],
        'volume' => (float)$candle['volume']
    ];
}

$formatted_prediction = null;
if ($prediction) {
    $last_candle_time = !empty($formatted_candles) ? end($formatted_candles)['time'] : time();
    $pred_time = strtotime($prediction['prediction_time']);
    
    // Ensure prediction is strictly after last candle
    if ($pred_time <= $last_candle_time) {
        $pred_time = $last_candle_time + $interval_seconds;
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
