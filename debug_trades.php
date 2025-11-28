<?php
require_once 'includes/db.php';

try {
    $stmt = $pdo->query("SELECT * FROM trades");
    $trades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Total Trades: " . count($trades) . "\n";
    print_r($trades);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
