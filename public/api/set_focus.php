<?php
header('Content-Type: application/json');

// Input: { "symbol": "BTCUSDT" }
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['symbol'])) {
    echo json_encode(['success' => false, 'error' => 'Symbol required']);
    exit;
}

$symbol = strtoupper($input['symbol']);
$focusFile = __DIR__ . '/../../backend/focus.json';

$data = [
    'symbol' => $symbol,
    'timestamp' => time()
];

try {
    file_put_contents($focusFile, json_encode($data));
    echo json_encode(['success' => true, 'symbol' => $symbol]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
