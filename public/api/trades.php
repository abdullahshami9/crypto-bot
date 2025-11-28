<?php
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);
require_once '../../includes/db.php';

// Handle POST request to close a trade
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['action'])) {
        if ($data['action'] === 'close' && isset($data['trade_id'])) {
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
        } elseif ($data['action'] === 'close_all') {
            try {
                // Fetch all open trades
                $stmt = $pdo->query("SELECT * FROM trades WHERE status = 'OPEN'");
                $openTrades = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $count = 0;
                foreach ($openTrades as $trade) {
                    // Get current price
                    $stmtPrice = $pdo->prepare("SELECT price FROM coins WHERE symbol = ?");
                    $stmtPrice->execute([$trade['symbol']]);
                    $coin = $stmtPrice->fetch(PDO::FETCH_ASSOC);
                    $currentPrice = $coin ? $coin['price'] : $trade['entry_price'];
                    
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
                    $updateStmt = $pdo->prepare("UPDATE trades SET status = 'CLOSED', exit_price = ?, exit_time = NOW(), pnl = ?, exit_reason = 'Manual Close All' WHERE id = ?");
                    $updateStmt->execute([$currentPrice, $pnlAmount, $trade['id']]);
                    
                    // Update Portfolio Balance
                    $returnAmount = ($entryPrice * $quantity) + $pnlAmount;
                    $updatePortfolio = $pdo->prepare("UPDATE portfolio SET balance = balance + ? WHERE id = 1");
                    $updatePortfolio->execute([$returnAmount]);
                    
                    $count++;
                }
                
                echo json_encode(['success' => true, 'message' => "Closed $count trades successfully"]);
            } catch (PDOException $e) {
                echo json_encode(['error' => $e->getMessage()]);
            }
            exit;
        }
    }
}

try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    
    if (!$userId) {
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    // Get User's Portfolio ID
    $stmtPort = $pdo->prepare("SELECT * FROM portfolio WHERE user_id = ?");
    $stmtPort->execute([$userId]);
    $portfolio = $stmtPort->fetch(PDO::FETCH_ASSOC);
    
    if (!$portfolio) {
        // Fallback if no portfolio exists (shouldn't happen with new auth)
        echo json_encode(['active' => [], 'history' => [], 'portfolio' => ['balance' => 0]]);
        exit;
    }
    
    $portfolioId = $portfolio['id'];

    // Fetch Active Trades for this Portfolio
    $stmt = $pdo->prepare("SELECT t.*, c.price as current_price FROM trades t LEFT JOIN coins c ON t.symbol = c.symbol WHERE t.status = 'OPEN' AND t.portfolio_id = ? ORDER BY t.entry_time DESC");
    $stmt->execute([$portfolioId]);
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

    // Fetch Closed Trades (Limit 50) for this Portfolio
    $stmt = $pdo->prepare("SELECT * FROM trades WHERE status = 'CLOSED' AND portfolio_id = ? ORDER BY exit_time DESC LIMIT 50");
    $stmt->execute([$portfolioId]);
    $closedTrades = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'active' => $activeTrades,
        'history' => $closedTrades,
        'portfolio' => $portfolio
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
