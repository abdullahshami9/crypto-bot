<?php
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);
require_once '../../includes/db.php';

// Handle POST request to close a trade
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['action']) && $data['action'] === 'close' && isset($data['trade_id'])) {
        try {
            $tradeId = $data['trade_id'];
            
            // Get trade details
            $stmt = $pdo->prepare("SELECT * FROM trades WHERE id = ?");
            $stmt->execute([$tradeId]);
            $trade = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($trade && $trade['status'] === 'OPEN') {
                // Get current price
                $stmtPrice = $pdo->prepare("SELECT price FROM coins WHERE symbol = ?");
                $stmtPrice->execute([$trade['symbol']]);
                $coin = $stmtPrice->fetch(PDO::FETCH_ASSOC);
                $currentPrice = $coin ? $coin['price'] : $trade['entry_price']; // Fallback
                
                // Calculate PnL
                $entryPrice = $trade['entry_price'];
                $quantity = $trade['quantity'];
                $type = isset($trade['type']) ? $trade['type'] : 'LONG';
                
                if ($type === 'LONG') {
                    $pnlPct = ($currentPrice - $entryPrice) / $entryPrice;
                } else {
                    $pnlPct = ($entryPrice - $currentPrice) / $entryPrice;
                }
                
                $pnlAmount = ($entryPrice * $quantity) * $pnlPct;
                
                // Close Trade
                $updateStmt = $pdo->prepare("UPDATE trades SET status = 'CLOSED', exit_price = ?, exit_time = NOW(), pnl = ?, exit_reason = 'Manual Close' WHERE id = ?");
                $updateStmt->execute([$currentPrice, $pnlAmount, $tradeId]);
                
                // Update Portfolio Balance
                // Return initial margin + PnL
                $returnAmount = ($entryPrice * $quantity) + $pnlAmount;
                $updatePortfolio = $pdo->prepare("UPDATE portfolio SET balance = balance + ? WHERE id = 1");
                $updatePortfolio->execute([$returnAmount]);
                
                echo json_encode(['success' => true, 'message' => 'Trade closed successfully']);
            } else {
                echo json_encode(['error' => 'Trade not found or already closed']);
            }
        } catch (PDOException $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }
}

try {
    // Fetch Active Trades
    $stmt = $pdo->query("SELECT t.*, c.price as current_price FROM trades t LEFT JOIN coins c ON t.symbol = c.symbol WHERE t.status = 'OPEN' ORDER BY t.entry_time DESC");
    $activeTrades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate PnL for Active Trades
    if ($activeTrades) {
        foreach ($activeTrades as &$trade) {
            $currentPrice = isset($trade['current_price']) ? $trade['current_price'] : $trade['entry_price'];
            $entryPrice = $trade['entry_price'];
            $type = isset($trade['type']) ? $trade['type'] : 'LONG';
            
            if ($type === 'LONG') {
                $pnlPct = ($currentPrice - $entryPrice) / $entryPrice;
            } else {
                $pnlPct = ($entryPrice - $currentPrice) / $entryPrice;
            }
            
            $trade['pnl_pct'] = $pnlPct * 100;
            $trade['pnl_amount'] = ($entryPrice * $trade['quantity']) * $pnlPct;
        }
    } else {
        $activeTrades = [];
    }

    // Fetch Closed Trades (Limit 50)
    $stmt = $pdo->query("SELECT * FROM trades WHERE status = 'CLOSED' ORDER BY exit_time DESC LIMIT 50");
    $closedTrades = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Portfolio Balance
    $stmt = $pdo->query("SELECT * FROM portfolio WHERE id = 1");
    $portfolio = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'active' => $activeTrades,
        'history' => $closedTrades,
        'portfolio' => $portfolio
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
