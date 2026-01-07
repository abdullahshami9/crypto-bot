<?php
require_once '../includes/db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crypto Intelligence | Daily Analysis</title>
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
        <div class="max-w-7xl mx-auto space-y-8">
            
            <!-- Page Header -->
            <div>
                <h1 class="text-3xl font-bold mb-2">Crypto Highs & Lows Analysis</h1>
                <div class="flex items-center gap-2 text-text-secondary text-sm">
                    <span class="font-bold text-accent-orange text-lg">BINANCE</span>
                    <span class="px-2 py-0.5 rounded bg-accent-orange/10 text-accent-orange text-xs font-bold">LIVE</span>
                    <span>Tracking All-Time Highs & Lows on Binance</span>
                </div>
            </div>

            <!-- Analysis Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                
                <!-- Highs Layout -->
                <div class="bg-bg-card rounded-2xl border border-border p-6 shadow-sm">
                    <h2 class="text-xl font-bold mb-6 flex items-center gap-2">
                        Coins at All-Time High
                    </h2>
                    <div id="daily-highs-container" class="space-y-4">
                        <div class="animate-pulse flex space-x-4">
                            <div class="flex-1 space-y-4 py-1">
                                <div class="h-40 bg-bg rounded"></div>
                                <div class="h-40 bg-bg rounded"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lows Layout -->
                <div class="bg-bg-card rounded-2xl border border-border p-6 shadow-sm">
                    <h2 class="text-xl font-bold mb-6 flex items-center gap-2">
                        Coins at All-Time Low
                    </h2>
                    <div id="daily-lows-container" class="space-y-4">
                        <div class="animate-pulse flex space-x-4">
                            <div class="flex-1 space-y-4 py-1">
                                <div class="h-40 bg-bg rounded"></div>
                                <div class="h-40 bg-bg rounded"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Market Insights -->
            <div class="bg-bg-card rounded-2xl border border-border p-6 shadow-sm">
                <h2 class="text-xl font-bold mb-4">Market Insights</h2>
                <div id="daily-insights-container" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- JS Populated -->
                </div>
            </div>
            
            <!-- Footer -->
            <footer class="flex justify-between items-center text-xs text-text-muted mt-10 border-t border-border pt-6">
                <div class="flex gap-4">
                    <a href="#" class="hover:text-text-primary">About</a>
                    <a href="#" class="hover:text-text-primary">Support</a>
                    <a href="#" class="hover:text-text-primary">Terms</a>
                    <a href="#" class="hover:text-text-primary">Privacy</a>
                </div>
                <div>© 2024 Crypto Analysis Platform</div>
            </footer>

        </div>
    </main>

    <script src="assets/js/app.js"></script>
</body>
</html>
