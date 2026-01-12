<?php
header('Content-Type: application/json');
require_once '../../includes/db.php';

// Check if user is logged in
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$symbol = isset($input['symbol']) ? trim(strtoupper($input['symbol'])) : '';

if (empty($symbol)) {
    echo json_encode(['success' => false, 'error' => 'Symbol is required']);
    exit;
}

// Compatibility helper for str_ends_with (PHP < 8.0)
function endsWith($haystack, $needle) {
    $length = strlen($needle);
    if ($length == 0) {
        return true;
    }
    return (substr($haystack, -$length) === $needle);
}

// Try to guess the correct pair if suffix is missing
$suffixes = ['USDT', 'BUSD', 'USDC', 'BTC', 'ETH', 'BNB'];
$has_suffix = false;
foreach ($suffixes as $s) {
    if (endsWith($symbol, $s)) {
        $has_suffix = true;
        break;
    }
}

$try_symbols = [];
if ($has_suffix) {
    $try_symbols[] = $symbol;
} else {
    $try_symbols[] = $symbol . 'USDT';
    $try_symbols[] = $symbol; // Try raw just in case
}

$found_data = null;

foreach ($try_symbols as $s) {
    $binanceUrl = "https://api.binance.com/api/v3/ticker/24hr?symbol=" . $s;

    $context = stream_context_create([
        'http' => [
            'ignore_errors' => true,
            'header' => "User-Agent: CryptoIntel/1.0\r\n"
        ]
    ]);

    $response = @file_get_contents($binanceUrl, false, $context);

    if ($response) {
        $data = json_decode($response, true);
        if (isset($data['symbol']) && isset($data['lastPrice'])) {
            // Check for error code in response even if HTTP 200 (Binance sometimes does this, though usually 400)
            if (!isset($data['code'])) {
                $found_data = $data;
                break;
            }
        }
    }
}

if ($found_data) {
    try {
        $final_symbol = $found_data['symbol'];
        $price = $found_data['lastPrice'];
        $volume = $found_data['quoteVolume'];
        $change = $found_data['priceChangePercent'];
        $ath = $price; // Initial assumption
        $atl = $price;

        $stmt = $pdo->prepare("
            INSERT INTO coins (symbol, price, volume, price_change_24h, ath, atl)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            price = VALUES(price),
            volume = VALUES(volume),
            price_change_24h = VALUES(price_change_24h)
        ");
        $stmt->execute([$final_symbol, $price, $volume, $change, $ath, $atl]);

        echo json_encode(['success' => true, 'symbol' => $final_symbol, 'message' => 'Coin added successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Coin not found on Binance']);
}
?>
