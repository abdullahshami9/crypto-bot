<?php
header('Content-Type: application/json');
require_once '../../includes/db.php';

// Coins to analyze (Reduced list to prevent timeouts)
$symbols = [
    'BTCUSDT', 'ETHUSDT', 'SOLUSDT', 'AVAXUSDT', 
    'ZECUSDT', 'FILUSDT', 'VETUSDT', 'FTMUSDT'
];
set_time_limit(60); // Allow script to run for 60 seconds

// Function to fetch historical data (Monthly klines for ATH/ATL)
function getCoinData($symbol) {
    // Fetch Monthly candles to find ATH/ATL efficiently
    // limit 1000 months is ~83 years, covers all crypto history
    $url = "https://api.binance.com/api/v3/klines?symbol={$symbol}&interval=1M&limit=1000";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $output = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($output, true);
    
    if (!is_array($data) || empty($data)) {
        return null;
    }
    
    $ath = 0;
    $atl = PHP_FLOAT_MAX;
    $currentPrice = 0;
    
    // Iterate through all historical candles
    foreach ($data as $candle) {
        $high = floatval($candle[2]);
        $low = floatval($candle[3]);
        $close = floatval($candle[4]);
        
        if ($high > $ath) $ath = $high;
        if ($low < $atl) $atl = $low;
        
        $currentPrice = $close; // Last candle's close is current price (roughly) or use separate ticker call
    }

    // Get live price for better accuracy
    $tickerUrl = "https://api.binance.com/api/v3/ticker/price?symbol={$symbol}";
    $ch2 = curl_init();
    curl_setopt($ch2, CURLOPT_URL, $tickerUrl);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
    $tickerOutput = curl_exec($ch2);
    curl_close($ch2);
    $tickerData = json_decode($tickerOutput, true);
    if (isset($tickerData['price'])) {
        $currentPrice = floatval($tickerData['price']);
    }

    $name = getCoinName($symbol);

    return [
        'symbol' => str_replace('USDT', '', $symbol),
        'name' => $name,
        'price' => $currentPrice,
        'ath' => $ath,
        'atl' => $atl
    ];
}

function getCoinName($symbol) {
    $map = [
        'BTCUSDT' => 'Bitcoin', 'ETHUSDT' => 'Ethereum', 'BNBUSDT' => 'Binance Coin',
        'SOLUSDT' => 'Solana', 'XRPUSDT' => 'Ripple', 'ADAUSDT' => 'Cardano',
        'AVAXUSDT' => 'Avalanche', 'DOGEUSDT' => 'Dogecoin', 'DOTUSDT' => 'Polkadot',
        'LINKUSDT' => 'Chainlink', 'ZECUSDT' => 'Zcash', 'FILUSDT' => 'Filecoin',
        'VETUSDT' => 'VeChain', 'FTMUSDT' => 'Fantom', 'LTCUSDT' => 'Litecoin'
    ];
    return isset($map[$symbol]) ? $map[$symbol] : $symbol;
}

$analysisResults = [];

// Process each coin
foreach ($symbols as $symbol) {
    $data = getCoinData($symbol);
    if ($data) {
        $price = $data['price'];
        $ath = $data['ath'];
        $atl = $data['atl'];
        
        $distFromAth = ($ath - $price) / $ath * 100; // 0% means it's at ATH
        $distFromAtl = ($price - $atl) / $atl * 100; // 0% means it's at ATL

        $data['dist_ath'] = $distFromAth;
        $data['dist_atl'] = $distFromAtl;
        
        $analysisResults[] = $data;
    }
}

// Sort for Highs (Closest to ATH)
$highs = $analysisResults;
usort($highs, function($a, $b) {
    return $a['dist_ath'] == $b['dist_ath'] ? 0 : ($a['dist_ath'] < $b['dist_ath'] ? -1 : 1);
});
$highs = array_slice($highs, 0, 3); // Top 3

// Sort for Lows (Closest to ATL - smallest distance from ATL)
// Actually we want coins that are DOWN from ATH significantly, essentially closer to ATL than ATH?
// Or just "Lowest compared to ATH"?
// Let's stick to user intent: "Dropped from $120 to $3". This means Price is much lower than ATH.
// Let's sort by % Drawdown from ATH descending
$lows = $analysisResults;
usort($lows, function($a, $b) {
    return $b['dist_ath'] == $a['dist_ath'] ? 0 : ($b['dist_ath'] < $a['dist_ath'] ? -1 : 1);
});
$lows = array_slice($lows, 0, 3);


// Format Highs for Response
$finalHighs = [];
foreach ($highs as $coin) {
    $finalHighs[] = [
        'symbol' => $coin['symbol'],
        'name' => $coin['name'],
        'price' => $coin['price'],
        'description' => 'Surged from $' . formatPrice($coin['atl']) . ' to $' . formatPrice($coin['price']),
        'analysis' => 'Near All-Time High',
        'action' => 'Consider Shorting',
        'badge' => 'Near ATH',
        'color' => 'red'
    ];
}

// Format Lows for Response
$finalLows = [];
foreach ($lows as $coin) {
    $finalLows[] = [
        'symbol' => $coin['symbol'],
        'name' => $coin['name'],
        'price' => $coin['price'],
        'description' => 'Dropped from $' . formatPrice($coin['ath']) . ' to $' . formatPrice($coin['price']),
        'analysis' => 'Deeply Undervalued',
        'action' => 'Consider Buying',
        'badge' => 'Near ATL',
        'color' => 'teal'
    ];
}

function formatPrice($val) {
    return $val < 1 ? number_format($val, 4) : number_format($val, 2);
}

// Insights (Dynamic)
$insights = [];
if (count($finalHighs) > 0) {
    $topHigh = $finalHighs[0];
    $insights[] = [
        'type' => 'hot',
        'title' => 'Overheated Market:',
        'text' => "{$topHigh['name']} is trading very close to its ATH. RSI likely overbought."
    ];
}
if (count($finalLows) > 0) {
    $topLow = $finalLows[0];
    $insights[] = [
        'type' => 'cold',
        'title' => 'Bargain Opportunity:',
        'text' => "{$topLow['name']} has seen a massive drawdown from its peak. Potential reversal zone."
    ];
}

echo json_encode([
    'highs' => $finalHighs,
    'lows' => $finalLows,
    'insights' => $insights
]);
