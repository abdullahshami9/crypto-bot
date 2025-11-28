<?php
require_once '../includes/db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
if ($_SESSION['role'] !== 'admin') {
    header("Location: chart.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crypto Intelligence | Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'], mono: ['JetBrains Mono', 'monospace'] },
                    colors: {
                        bg: { DEFAULT: 'var(--bg-main)', card: 'var(--bg-card)', hover: 'var(--bg-hover)' },
                        text: { primary: 'var(--text-main)', secondary: 'var(--text-secondary)', muted: 'var(--text-muted)' },
                        accent: { blue: '#2962ff', teal: '#0ecb81', red: '#f6465d', orange: '#f0b90b' },
                        border: 'var(--border-color)'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-bg text-text-primary h-screen flex flex-col overflow-hidden">

    <?php include 'includes/header.php'; ?>

    <main class="flex-1 overflow-y-auto p-6">
        <div class="max-w-7xl mx-auto space-y-6">
            
            <!-- Header & Bot Control -->
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold">Dashboard</h1>
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2 bg-bg-card border border-border px-3 py-1.5 rounded-lg">
                        <div id="bot-status-indicator" class="w-2.5 h-2.5 rounded-full bg-accent-teal animate-pulse"></div>
                        <span class="text-sm font-medium">Bot Active</span>
                    </div>
                    <button id="toggle-bot-btn" class="px-4 py-2 text-sm font-bold bg-accent-red/10 text-accent-red hover:bg-accent-red hover:text-white rounded-lg transition-colors">Stop Trading</button>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="p-4 rounded-xl bg-bg-card border border-border">
                    <div class="text-sm text-text-secondary mb-1">Total Balance</div>
                    <div class="text-2xl font-mono font-bold" id="dash-balance">--</div>
                    <div class="text-xs text-accent-teal mt-1">+0.00% (24h)</div>
                </div>
                <div class="p-4 rounded-xl bg-bg-card border border-border">
                    <div class="text-sm text-text-secondary mb-1">Total PnL</div>
                    <div class="text-2xl font-mono font-bold" id="dash-pnl">--</div>
                    <div class="text-xs text-text-muted mt-1">All Time</div>
                </div>
                <div class="p-4 rounded-xl bg-bg-card border border-border">
                    <div class="text-sm text-text-secondary mb-1">Active Trades</div>
                    <div class="text-2xl font-mono font-bold" id="dash-active-count">--</div>
                    <div class="text-xs text-text-muted mt-1">Positions</div>
                </div>
                <div class="p-4 rounded-xl bg-bg-card border border-border">
                    <div class="text-sm text-text-secondary mb-1">Win Rate</div>
                    <div class="text-2xl font-mono font-bold" id="dash-winrate">--%</div>
                    <div class="text-xs text-text-muted mt-1">Last 50 Trades</div>
                </div>
            </div>

            <!-- Active Trades -->
            <div class="rounded-xl bg-bg-card border border-border overflow-hidden">
                <div class="p-4 border-b border-border flex justify-between items-center">
                    <h2 class="text-lg font-bold">Active Positions</h2>
                    <button id="close-all-btn" class="px-3 py-1.5 text-xs font-bold bg-accent-red text-white rounded hover:bg-red-600 transition-colors">Close All Positions</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-bg border-b border-border text-text-secondary">
                            <tr>
                                <th class="px-4 py-3 font-medium">Symbol</th>
                                <th class="px-4 py-3 font-medium">Type</th>
                                <th class="px-4 py-3 font-medium">Entry Price</th>
                                <th class="px-4 py-3 font-medium">Current Price</th>
                                <th class="px-4 py-3 font-medium">PnL</th>
                                <th class="px-4 py-3 font-medium">Reasoning</th>
                                <th class="px-4 py-3 font-medium text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody id="dash-trades-body" class="divide-y divide-border">
                            <!-- JS Populated -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Activity / Signals -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Recent Signals -->
                <div class="rounded-xl bg-bg-card border border-border overflow-hidden">
                    <div class="p-4 border-b border-border">
                        <h2 class="text-lg font-bold">Recent AI Signals</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-bg border-b border-border text-text-secondary">
                                <tr>
                                    <th class="px-4 py-3 font-medium">Symbol</th>
                                    <th class="px-4 py-3 font-medium">Signal</th>
                                    <th class="px-4 py-3 font-medium">Score</th>
                                    <th class="px-4 py-3 font-medium">Time</th>
                                </tr>
                            </thead>
                            <tbody id="dash-signals-body" class="divide-y divide-border">
                                <!-- JS Populated -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Trade History -->
                <div class="rounded-xl bg-bg-card border border-border overflow-hidden">
                    <div class="p-4 border-b border-border">
                        <h2 class="text-lg font-bold">Trade History</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-bg border-b border-border text-text-secondary">
                                <tr>
                                    <th class="px-4 py-3 font-medium">Symbol</th>
                                    <th class="px-4 py-3 font-medium">PnL</th>
                                    <th class="px-4 py-3 font-medium">Exit Reason</th>
                                    <th class="px-4 py-3 font-medium">Time</th>
                                </tr>
                            </thead>
                            <tbody id="dash-history-body" class="divide-y divide-border">
                                <!-- JS Populated -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <script src="assets/js/app.js"></script>
</body>
</html>
