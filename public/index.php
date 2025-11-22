<?php
require_once '../includes/db.php';

// Fetch Portfolio
$stmt = $pdo->query("SELECT * FROM portfolio WHERE id = 1");
$portfolio = $stmt->fetch();

// Fetch Active Trades
$stmt = $pdo->query("SELECT * FROM trades WHERE status = 'OPEN' ORDER BY entry_time DESC");
$active_trades = $stmt->fetchAll();

// Fetch Recent Signals
$stmt = $pdo->query("SELECT * FROM signals ORDER BY created_at DESC LIMIT 5");
$signals = $stmt->fetchAll();

// Fetch Top Opportunities (High Score)
$stmt = $pdo->query("SELECT * FROM signals WHERE score > 50 ORDER BY score DESC LIMIT 5");
$opportunities = $stmt->fetchAll();

// Calculate Total PnL (Closed Trades)
$stmt = $pdo->query("SELECT SUM(pnl) as total_pnl FROM trades WHERE status = 'CLOSED'");
$pnl_data = $stmt->fetch();

$total_pnl = $pnl_data['total_pnl'] ? $pnl_data['total_pnl'] : 0;

?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crypto Intelligence Engine</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        gray: {
                            900: '#0f172a',
                            800: '#1e293b',
                            700: '#334155',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body { background-color: #0f172a; color: #e2e8f0; font-family: 'Inter', sans-serif; }
        .glass-panel {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
    </style>
</head>
<body class="min-h-screen p-6">

    <!-- Header -->
    <header class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 to-blue-500">
                Crypto Intelligence Engine
            </h1>
            <p class="text-gray-400 text-sm">AI-Driven Virtual Trading & Analysis</p>
        </div>
        <div class="text-right">
            <div class="text-sm text-gray-400">Virtual Balance</div>
            <div class="text-2xl font-mono font-bold text-green-400">$<?= number_format($portfolio['balance'], 2) ?></div>
            <div class="text-xs <?= $total_pnl >= 0 ? 'text-green-500' : 'text-red-500' ?>">
                Total PnL: $<?= number_format($total_pnl, 2) ?>
            </div>
        </div>
    </header>

    <!-- Main Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Active Trades -->
        <div class="lg:col-span-2 space-y-6">
            <div class="glass-panel rounded-xl p-6">
                <h2 class="text-xl font-semibold mb-4 flex items-center">
                    <span class="w-2 h-2 bg-green-500 rounded-full mr-2 animate-pulse"></span>
                    Active Trades
                </h2>
                <?php if (count($active_trades) > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="text-gray-500 border-b border-gray-700">
                                    <th class="pb-2">Symbol</th>
                                    <th class="pb-2">Entry</th>
                                    <th class="pb-2">Qty</th>
                                    <th class="pb-2">Current (Sim)</th>
                                    <th class="pb-2">PnL (Est)</th>
                                    <th class="pb-2">AI Rationale</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800">
                                <?php foreach ($active_trades as $trade): 
                                    // Fetch current price for sim
                                    $s_stmt = $pdo->prepare("SELECT price FROM coins WHERE symbol = ?");
                                    $s_stmt->execute([$trade['symbol']]);
                                    $curr = $s_stmt->fetch();
                                    $curr_price = $curr['price'] ? $curr['price'] : $trade['entry_price'];
                                    $pnl = ($curr_price - $trade['entry_price']) * $trade['quantity'];
                                    $pnl_class = $pnl >= 0 ? 'text-green-400' : 'text-red-400';
                                    
                                    // Fetch rationale
                                    $r_stmt = $pdo->prepare("SELECT rationale FROM signals WHERE symbol = ? AND created_at <= ? ORDER BY created_at DESC LIMIT 1");
                                    $r_stmt->execute([$trade['symbol'], $trade['entry_time']]);
                                    $sig = $r_stmt->fetch();
                                    $rationale = $sig ? $sig['rationale'] : 'N/A';
                                ?>
                                <tr>
                                    <td class="py-3 font-medium text-white"><?= htmlspecialchars($trade['symbol']) ?></td>
                                    <td class="py-3 text-gray-400">$<?= number_format($trade['entry_price'], 4) ?></td>
                                    <td class="py-3 text-gray-400"><?= number_format($trade['quantity'], 4) ?></td>
                                    <td class="py-3 text-gray-400">$<?= number_format($curr_price, 4) ?></td>
                                    <td class="py-3 font-mono <?= $pnl_class ?>">
                                        <?= $pnl >= 0 ? '+' : '' ?><?= number_format($pnl, 2) ?>
                                    </td>
                                    <td class="py-3 text-xs text-cyan-300 max-w-xs truncate" title="<?= htmlspecialchars($rationale) ?>">
                                        <?= htmlspecialchars($rationale) ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500 italic">No active trades running.</p>
                <?php endif; ?>
            </div>

            <!-- Market Intelligence -->
            <div class="glass-panel rounded-xl p-6">
                <h2 class="text-xl font-semibold mb-4 text-cyan-400">AI Market Intelligence</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($opportunities as $opp): ?>
                    <div class="bg-gray-800/50 p-4 rounded-lg border border-gray-700 hover:border-cyan-500/50 transition-colors">
                        <div class="flex justify-between items-start mb-2">
                            <h3 class="font-bold text-lg"><?= htmlspecialchars($opp['symbol']) ?></h3>
                            <span class="bg-cyan-900 text-cyan-300 text-xs px-2 py-1 rounded">Score: <?= $opp['score'] ?></span>
                        </div>
                        <p class="text-xs text-gray-400 mb-2"><?= htmlspecialchars($opp['rationale']) ?></p>
                        <div class="text-xs text-gray-500"><?= $opp['created_at'] ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar: Recent Signals & Stats -->
        <div class="space-y-6">
            <div class="glass-panel rounded-xl p-6">
                <h2 class="text-lg font-semibold mb-4">Recent Signals</h2>
                <div class="space-y-4">
                    <?php foreach ($signals as $signal): ?>
                    <div class="flex items-center justify-between text-sm border-b border-gray-800 pb-2 last:border-0">
                        <div>
                            <div class="font-medium text-white"><?= htmlspecialchars($signal['symbol']) ?></div>
                            <div class="text-xs text-gray-500"><?= htmlspecialchars($signal['signal_type']) ?></div>
                        </div>
                        <div class="text-right">
                            <div class="text-cyan-400 font-mono"><?= $signal['score'] ?></div>
                            <div class="text-xs text-gray-600">Score</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="glass-panel rounded-xl p-6 bg-gradient-to-br from-blue-900/20 to-purple-900/20">
                <h2 class="text-lg font-semibold mb-2">System Status</h2>
                <?php
                    // Fetch learning stats
                    $l_stmt = $pdo->query("SELECT COUNT(*) as count FROM trade_learning");
                    $learned_count = $l_stmt->fetch()['count'];
                    
                    // Fetch total trades
                    $t_stmt = $pdo->query("SELECT COUNT(*) as count FROM trades");
                    $total_trades = $t_stmt->fetch()['count'];
                ?>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-400">Engine</span>
                        <span class="text-green-400">● Online</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Data Feed</span>
                        <span class="text-green-400">● Connected</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">AI Model</span>
                        <span class="text-blue-400">Active</span>
                    </div>
                    <div class="border-t border-gray-700 my-2 pt-2">
                        <div class="flex justify-between">
                            <span class="text-gray-400">Trades Executed</span>
                            <span class="text-white"><?= $total_trades ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Experience (Learned)</span>
                            <span class="text-purple-400"><?= $learned_count ?> Events</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Simple auto-refresh for dashboard data
        setTimeout(() => {
            window.location.reload();
        }, 30000); // Refresh every 30s
    </script>
</body>
</html>
