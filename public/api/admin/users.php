<?php
header('Content-Type: application/json');
require_once '../../includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Admin Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    // Fetch all customers with their portfolio
    $stmt = $pdo->query("
        SELECT 
            u.id, u.username, u.email, u.created_at,
            p.id as portfolio_id, p.balance
        FROM users u
        LEFT JOIN portfolio p ON u.id = p.user_id
        WHERE u.role = 'customer'
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $userStats = [];

    foreach ($users as $user) {
        $pid = $user['portfolio_id'];
        
        if ($pid) {
            // Total Trades
            $stmtTrades = $pdo->prepare("SELECT COUNT(*) FROM trades WHERE portfolio_id = ? AND status = 'CLOSED'");
            $stmtTrades->execute([$pid]);
            $totalTrades = $stmtTrades->fetchColumn();

            // Total PnL (Realized)
            // We need to calculate PnL from closed trades. 
            // Assuming we store PnL in DB or calculate it. 
            // Let's calculate from entry/exit price if not stored explicitly, 
            // but ideally we should have a 'pnl' column. 
            // For now, let's sum the calculated PnL on the fly or use a stored column if we added one.
            // We didn't add a 'pnl' column to trades table in schema.sql explicitly visible in recent edits, 
            // but trades.php calculates it. Let's calculate it here too.
            
            $stmtPnL = $pdo->prepare("SELECT * FROM trades WHERE portfolio_id = ? AND status = 'CLOSED'");
            $stmtPnL->execute([$pid]);
            $trades = $stmtPnL->fetchAll(PDO::FETCH_ASSOC);
            
            $totalPnL = 0;
            $wins = 0;
            $firstTradeDate = null;
            $lastTradeDate = null;

            foreach ($trades as $t) {
                $entry = $t['entry_price'];
                $exit = $t['exit_price'];
                $qty = $t['quantity'];
                $type = $t['type']; // LONG/SHORT
                
                $tradePnL = 0;
                if ($type === 'LONG') {
                    $tradePnL = ($exit - $entry) * $qty;
                } else {
                    $tradePnL = ($entry - $exit) * $qty;
                }
                
                $totalPnL += $tradePnL;
                if ($tradePnL > 0) $wins++;
                
                $tradeDate = strtotime($t['entry_time']);
                if (!$firstTradeDate || $tradeDate < $firstTradeDate) $firstTradeDate = $tradeDate;
                if (!$lastTradeDate || $tradeDate > $lastTradeDate) $lastTradeDate = $tradeDate;
            }

            // Win Rate
            $winRate = $totalTrades > 0 ? ($wins / $totalTrades) * 100 : 0;

            // Trades Per Day
            $tradesPerDay = 0;
            if ($firstTradeDate && $lastTradeDate) {
                $days = max(1, ceil(($lastTradeDate - $firstTradeDate) / (60 * 60 * 24)));
                $tradesPerDay = $totalTrades / $days;
            } elseif ($totalTrades > 0) {
                $tradesPerDay = $totalTrades; // All in one day
            }

            // ROI % (Total PnL / Initial Balance 1000) * 100
            // Assuming 1000 initial.
            $roi = ($totalPnL / 1000) * 100;

            $userStats[] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'balance' => $user['balance'],
                'total_trades' => $totalTrades,
                'total_pnl' => $totalPnL,
                'win_rate' => $winRate,
                'trades_per_day' => $tradesPerDay,
                'roi' => $roi,
                'joined' => $user['created_at']
            ];
        } else {
            // No portfolio (shouldn't happen)
            $userStats[] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'balance' => 0,
                'total_trades' => 0,
                'total_pnl' => 0,
                'win_rate' => 0,
                'trades_per_day' => 0,
                'roi' => 0,
                'joined' => $user['created_at']
            ];
        }
    }

    echo json_encode($userStats);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
