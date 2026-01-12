<?php
header('Content-Type: application/json');
if (file_exists(__DIR__ . '/../../includes/db.php')) {
    require_once __DIR__ . '/../../includes/db.php';
} elseif (file_exists(__DIR__ . '/../includes/db.php')) {
    require_once __DIR__ . '/../includes/db.php';
} else {
    // Fallback or error
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Database configuration not found']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$symbol = isset($input['symbol']) ? $input['symbol'] : '';

if (empty($symbol)) {
    echo json_encode(['error' => 'Symbol is required']);
    exit;
}

// Sanitize symbol to prevent command injection
$symbol = preg_replace('/[^A-Z0-9]/', '', strtoupper($symbol));

// Construct command to run python script
// public/api/sync_coin.php -> ../../backend/sync_coin.py
$pythonPath = "C:\\Users\\abdullah.shahmeer\\AppData\\Local\\Programs\\Python\\Python313\\python.exe";
$scriptPath = __DIR__ . "/../../backend/sync_coin.py";
$command = "\"$pythonPath\" \"$scriptPath\" --symbol " . escapeshellarg($symbol) . " 2>&1";
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
