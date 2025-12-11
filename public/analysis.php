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

            <!-- Signals Section -->
            <div class="space-y-4">
                <div class="flex items-center justify-between cursor-pointer group" onclick="toggleSection('signals-section', 'signals-arrow')">
                    <h2 class="text-xl font-bold flex items-center gap-2">
                        <span class="w-1 h-6 bg-accent-blue rounded-full"></span>
                        AI Signals
                    </h2>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-mono text-text-secondary">Real-time Analysis</span>
                        <svg id="signals-arrow" class="w-5 h-5 text-text-secondary transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                </div>
                
                <div id="signals-section" class="rounded-xl bg-bg-card border border-border overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-bg border-b border-border text-text-secondary">
                                <tr>
                                    <th class="px-6 py-4 font-medium">Symbol</th>
                                    <th class="px-6 py-4 font-medium">Signal</th>
                                    <th class="px-6 py-4 font-medium">Score</th>
                                    <th class="px-6 py-4 font-medium">Time</th>
                                    <th class="px-6 py-4 font-medium text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody id="analysis-signals-table-body" class="divide-y divide-border/50">
                                <tr><td colspan="5" class="px-6 py-8 text-center text-text-secondary italic">Loading signals...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Predictions Section -->
            <div class="space-y-4 pt-6 border-t border-border/50">
                <div class="flex items-center justify-between cursor-pointer group" onclick="toggleSection('predictions-section', 'predictions-arrow')">
                    <h2 class="text-xl font-bold flex items-center gap-2">
                        <span class="w-1 h-6 bg-purple-500 rounded-full"></span>
                        Price Predictions
                    </h2>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-mono text-text-secondary">Machine Learning Forecasts</span>
                        <svg id="predictions-arrow" class="w-5 h-5 text-text-secondary transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                </div>

                <div id="predictions-section" class="rounded-xl bg-bg-card border border-border overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-bg border-b border-border text-text-secondary">
                                <tr>
                                    <th class="px-6 py-4 font-medium">Symbol</th>
                                    <th class="px-6 py-4 font-medium">Interval</th>
                                    <th class="px-6 py-4 font-medium">Predicted Close</th>
                                    <th class="px-6 py-4 font-medium">Confidence</th>
                                    <th class="px-6 py-4 font-medium">Target Time</th>
                                </tr>
                            </thead>
                            <tbody id="analysis-predictions-table-body" class="divide-y divide-border/50">
                                <tr><td colspan="5" class="px-6 py-8 text-center text-text-secondary italic">Loading predictions...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <script>
                function toggleSection(sectionId, arrowId) {
                    const section = document.getElementById(sectionId);
                    const arrow = document.getElementById(arrowId);
                    if (section.classList.contains('hidden')) {
                        section.classList.remove('hidden');
                        arrow.style.transform = 'rotate(0deg)';
                    } else {
                        section.classList.add('hidden');
                        arrow.style.transform = 'rotate(-90deg)';
                    }
                }
            </script>

        </div>
    </main>

    <script src="assets/js/app.js"></script>
</body>
</html>
