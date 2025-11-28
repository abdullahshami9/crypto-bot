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
    <title>Crypto Intelligence | Analysis</title>
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
            <h1 class="text-2xl font-bold">Market Analysis & AI Signals</h1>

            <!-- Signals Table -->
            <div class="rounded-xl bg-bg-card border border-border overflow-hidden">
                <div class="p-4 border-b border-border">
                    <h2 class="text-lg font-bold">Latest AI Signals</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-bg border-b border-border text-text-secondary">
                            <tr>
                                <th class="px-4 py-3 font-medium">Symbol</th>
                                <th class="px-4 py-3 font-medium">Signal</th>
                                <th class="px-4 py-3 font-medium">Score</th>
                                <th class="px-4 py-3 font-medium">Rationale</th>
                                <th class="px-4 py-3 font-medium">LLM Analysis</th>
                                <th class="px-4 py-3 font-medium">Time</th>
                            </tr>
                        </thead>
                        <tbody id="analysis-signals-body" class="divide-y divide-border">
                            <!-- JS Populated -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Predictions Table -->
            <div class="rounded-xl bg-bg-card border border-border overflow-hidden">
                <div class="p-4 border-b border-border">
                    <h2 class="text-lg font-bold">Price Predictions</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-bg border-b border-border text-text-secondary">
                            <tr>
                                <th class="px-4 py-3 font-medium">Symbol</th>
                                <th class="px-4 py-3 font-medium">Interval</th>
                                <th class="px-4 py-3 font-medium">Predicted Close</th>
                                <th class="px-4 py-3 font-medium">Confidence</th>
                                <th class="px-4 py-3 font-medium">Target Time</th>
                            </tr>
                        </thead>
                        <tbody id="analysis-predictions-body" class="divide-y divide-border">
                            <!-- JS Populated -->
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <script src="assets/js/app.js"></script>
</body>
</html>
