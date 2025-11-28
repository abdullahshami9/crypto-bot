<?php
header('Content-Type: application/json');
require_once '../../includes/db.php';

try {
    // Fetch latest signals
    $stmt = $pdo->query("
        SELECT s.*, c.price as current_price 
        FROM signals s 
        LEFT JOIN coins c ON s.symbol = c.symbol 
        ORDER BY s.created_at DESC 
        LIMIT 50
    ");
    $signals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch latest predictions
    $stmtPred = $pdo->query("
        SELECT p.*, c.price as current_price 
        FROM predictions p 
        LEFT JOIN coins c ON p.symbol = c.symbol 
        ORDER BY p.created_at DESC 
        LIMIT 20
    ");
    $predictions = $stmtPred->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'signals' => $signals,
        'predictions' => $predictions
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
