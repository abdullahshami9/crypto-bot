<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crypto Intelligence</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- TradingView Lightweight Charts -->
    <script src="https://unpkg.com/lightweight-charts@4.1.1/dist/lightweight-charts.standalone.production.js"></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
        <!-- Dashboard Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Main Chart Card Removed (Moved to chart.php) -->
            <div class="lg:col-span-2 google-card p-6 flex items-center justify-center min-h-[200px]">
                <div class="text-center">
                    <h2 class="text-xl font-medium mb-2">Live Market Chart</h2>
                    <p class="text-gray-500 mb-4">View detailed real-time charts and AI predictions.</p>
                    <a href="chart.php" class="bg-[var(--accent-color)] text-white px-6 py-2 rounded-full font-medium hover:opacity-90 transition-opacity">
                        Open Live Graph
                    </a>
                </div>
            </div>

            <!-- Side Panel: Predictions & Stats -->
            <div class="space-y-6">
                <!-- Prediction Summary -->
                <div class="google-card p-6">
                    <h3 class="text-lg font-medium mb-4">AI Insight</h3>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center p-3 rounded-lg bg-[var(--hover-bg)]">
                            <span class="text-sm text-gray-500">Signal</span>
                            <span class="font-medium text-green-600">STRONG BUY</span>
                        </div>
                        <div class="flex justify-between items-center p-3 rounded-lg bg-[var(--hover-bg)]">
                            <span class="text-sm text-gray-500">Confidence</span>
                            <span class="font-medium">87%</span>
                        </div>
                        <div class="flex justify-between items-center p-3 rounded-lg bg-[var(--hover-bg)]">
                            <span class="text-sm text-gray-500">Predicted High</span>
                            <span class="font-medium">$98,450.00</span>
                        </div>
                    </div>
                    <button class="w-full mt-6 bg-[var(--accent-color)] text-white font-medium py-2 rounded-lg hover:opacity-90 transition-opacity">
                        View Detailed Report
                    </button>
                </div>

                <!-- Market Stats -->
                <div class="google-card p-6">
                    <h3 class="text-lg font-medium mb-4">Market Stats</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <div class="text-sm text-gray-500 mb-1">24h Volume</div>
                            <div class="font-medium">1.2B</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 mb-1">Market Cap</div>
                            <div class="font-medium">1.8T</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 mb-1">24h High</div>
                            <div class="font-medium">$99,000</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 mb-1">24h Low</div>
                            <div class="font-medium">$95,200</div>
                        </div>
                    </div>
                </div>
            </div>

        <!-- Recent Trades Section -->
        <div class="mt-8 google-card p-6">
            <h3 class="text-lg font-medium mb-4">Bot Trading History</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-sm text-gray-500 border-b border-[var(--border-color)]">
                            <th class="py-3 px-4">Time</th>
                            <th class="py-3 px-4">Symbol</th>
                            <th class="py-3 px-4">Type</th>
                            <th class="py-3 px-4">Entry</th>
                            <th class="py-3 px-4">Exit</th>
                            <th class="py-3 px-4">PnL</th>
                            <th class="py-3 px-4">Status</th>
                        </tr>
                    </thead>
                    <tbody id="trades-body" class="text-sm">
                        <!-- Trades injected by JS -->
                    </tbody>
                </table>
            </div>
        </div>
        </div>

    </main>

    <!-- App Script -->
    <script src="assets/js/app.js"></script>
</body>
</html>
