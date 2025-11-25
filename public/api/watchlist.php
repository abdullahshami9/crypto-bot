<?php
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

require_once '../../includes/db.php';

// Database Connection (Fallback if require fails)
if (!isset($pdo)) {
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
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (\PDOException $e) {
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    }
}

try {
    // Fetch top 10 coins with highest confidence predictions in the future
    // We prioritize 1h and 4h intervals for immediate trading relevance
    $sql = "
        SELECT 
            p.symbol, 
            p.interval,
            p.confidence_score, 
            p.predicted_close,
            p.prediction_time,
            c.price as current_price,
            c.price_change_24h
        FROM predictions p
        JOIN coins c ON p.symbol = c.symbol
        WHERE p.prediction_time > NOW()
        AND p.interval IN ('1h', '4h', '15m')
        AND p.confidence_score >= 95
        ORDER BY p.confidence_score DESC
        LIMIT 20
    ";

    $stmt = $pdo->query($sql);
    $watchlist = $stmt->fetchAll();

    // If no predictions, fallback to top volume coins
    if (empty($watchlist)) {
        $sql_fallback = "
            SELECT symbol, price as current_price, price_change_24h 
            FROM coins 
            ORDER BY volume DESC 
            LIMIT 5
        ";
        $stmt_fallback = $pdo->query($sql_fallback);
        $watchlist = $stmt_fallback->fetchAll();
        foreach ($watchlist as &$item) {
            $item['confidence_score'] = 0; // No prediction
        }
    }

    echo json_encode($watchlist);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
