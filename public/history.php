<?php
require_once '../includes/db.php';

// Fetch Closed Trades
$stmt = $pdo->query("SELECT * FROM trades WHERE status = 'CLOSED' ORDER BY exit_time DESC");
$history = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trade History - Crypto Intelligence</title>
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

    <header class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-2xl font-bold text-white">Trade History</h1>
            <a href="index.php" class="text-sm text-cyan-400 hover:text-cyan-300">← Back to Dashboard</a>
        </div>
    </header>

    <div class="glass-panel rounded-xl p-6">
        <?php if (count($history) > 0): ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="text-gray-500 border-b border-gray-700">
                            <th class="pb-2">Symbol</th>
                            <th class="pb-2">Entry Time</th>
                            <th class="pb-2">Exit Time</th>
                            <th class="pb-2">Entry Price</th>
                            <th class="pb-2">Exit Price</th>
                            <th class="pb-2">PnL</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        <?php foreach ($history as $trade): 
                            $pnl_class = $trade['pnl'] >= 0 ? 'text-green-400' : 'text-red-400';
                        ?>
                        <tr>
                            <td class="py-3 font-medium text-white"><?= htmlspecialchars($trade['symbol']) ?></td>
                            <td class="py-3 text-gray-400"><?= $trade['entry_time'] ?></td>
                            <td class="py-3 text-gray-400"><?= $trade['exit_time'] ?></td>
                            <td class="py-3 text-gray-400">$<?= number_format($trade['entry_price'], 4) ?></td>
                            <td class="py-3 text-gray-400">$<?= number_format($trade['exit_price'], 4) ?></td>
                            <td class="py-3 font-mono <?= $pnl_class ?>">
                                <?= $trade['pnl'] >= 0 ? '+' : '' ?><?= number_format($trade['pnl'], 2) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-gray-500 italic">No closed trades yet.</p>
        <?php endif; ?>
    </div>

</body>
</html>
