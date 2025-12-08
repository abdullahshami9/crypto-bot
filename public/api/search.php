<?php
header('Content-Type: application/json');
require_once '../../includes/db.php';

$query = isset($_GET['q']) ? $_GET['q'] : '';

if (strlen($query) < 1) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT symbol, price, price_change_24h FROM coins WHERE symbol LIKE ? ORDER BY volume DESC LIMIT 10");
    $stmt->execute(["%$query%"]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If no results specifically for a potential ticker search, try to fetch from Binance
    if (empty($results) && preg_match('/^[A-Z0-9]+$/', $query)) {
        // Try exact match or append USDT
        $searchSymbol = $query;
        // Check if it already ends with USDT or BTC (manual check for PHP < 8.0 compatibility)
        $lenUSDT = strlen('USDT');
        $lenBTC = strlen('BTC');
        $endsWithUSDT = (substr($searchSymbol, -$lenUSDT) === 'USDT');
        $endsWithBTC = (substr($searchSymbol, -$lenBTC) === 'BTC');

        if (!$endsWithUSDT && !$endsWithBTC) {
             $searchSymbol .= 'USDT';
        }

        $binanceUrl = "https://api.binance.com/api/v3/ticker/24hr?symbol=" . $searchSymbol;
        
        // Debug
        // file_put_contents('debug_search.log', "Fetching: $binanceUrl\n", FILE_APPEND);

        // Suppress warnings for 404s
        $context = stream_context_create([
            'http' => [
                'ignore_errors' => true,
                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36\r\n"
            ]
        ]);
        $response = @file_get_contents($binanceUrl, false, $context);
        
        // file_put_contents('debug_search.log', "Response: " . substr($response, 0, 100) . "\n", FILE_APPEND);

        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['symbol']) && isset($data['lastPrice'])) {
                // Determine if 400 bad request (symbol not found)
                if (isset($data['code']) && $data['code'] < 0) {
                     // Binance error
                } else {
                    // Valid coin data found, insert into DB
                    $symbol = $data['symbol'];
                    $price = $data['lastPrice'];
                    $volume = $data['quoteVolume'];
                    $change = $data['priceChangePercent'];
                    $ath = $price;
                    $atl = $price;

                    $insertStmt = $pdo->prepare("
                        INSERT INTO coins (symbol, price, volume, price_change_24h, ath, atl)
                        VALUES (?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE 
                        price = VALUES(price), 
                        volume = VALUES(volume), 
                        price_change_24h = VALUES(price_change_24h)
                    ");
                    $insertStmt->execute([$symbol, $price, $volume, $change, $ath, $atl]);

                    // Add to results
                    $results[] = [
                        'symbol' => $symbol,
                        'price' => $price,
                        'price_change_24h' => $change
                    ];
                }
            }
        }
    }

    echo json_encode($results);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
