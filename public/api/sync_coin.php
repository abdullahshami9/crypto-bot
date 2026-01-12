<?php
header('Content-Type: application/json');
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$symbol = $input['symbol'] ?? '';

if (empty($symbol)) {
    echo json_encode(['error' => 'Symbol is required']);
    exit;
}

// Sanitize symbol to prevent command injection
$symbol = preg_replace('/[^A-Z0-9]/', '', strtoupper($symbol));

// Construct command to run python script
// public/api/sync_coin.php -> ../../backend/sync_coin.py
$command = "python3 ../../backend/sync_coin.py --symbol " . escapeshellarg($symbol) . " 2>&1";
exec($command, $output, $return_var);

// Parse the output (looking for the JSON line)
$result = null;
foreach ($output as $line) {
    $decoded = json_decode($line, true);
    if ($decoded && isset($decoded['success'])) {
        $result = $decoded;
        break;
    }
}

if ($result) {
    echo json_encode($result);
} else {
    // If we didn't get valid JSON back, return the raw output for debugging
    echo json_encode([
        'error' => 'Failed to execute sync script',
        'details' => implode("\n", $output)
    ]);
}
?>
