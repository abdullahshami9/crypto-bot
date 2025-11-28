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
    echo json_encode($results);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
