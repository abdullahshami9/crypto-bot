<?php
header('Content-Type: application/json');

require_once '../../includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$configFile = '../../backend/trading_config.json';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['action']) && $data['action'] === 'toggle') {
        $config = [];
        if (file_exists($configFile)) {
            $config = json_decode(file_get_contents($configFile), true);
        }
        
        // Toggle or set default
        $currentState = isset($config['trading_enabled']) ? $config['trading_enabled'] : true;
        $config['trading_enabled'] = !$currentState;
        
        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
        
        echo json_encode(['success' => true, 'enabled' => $config['trading_enabled']]);
        exit;
    }
}

// GET request to check status
$config = [];
if (file_exists($configFile)) {
    $config = json_decode(file_get_contents($configFile), true);
}
$enabled = isset($config['trading_enabled']) ? $config['trading_enabled'] : true;

echo json_encode(['enabled' => $enabled]);
?>
