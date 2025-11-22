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
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="min-h-screen flex flex-col">

    <!-- Header -->
    <header class="border-b border-[var(--border-color)] bg-[var(--card-bg)] sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-8 h-8 text-[var(--accent-color)]" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zm6-4a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zm6-3a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"></path>
                </svg>
                <span class="text-xl font-medium text-[var(--text-color)]">Crypto Intelligence</span>
            </div>
            
            <div class="flex items-center gap-4">
                <div class="theme-toggle" id="theme-toggle">
                    <!-- Icon injected by JS -->
                </div>
                <div class="w-8 h-8 rounded-full bg-gray-300 overflow-hidden">
                    <img src="https://ui-avatars.com/api/?name=User&background=random" alt="User" class="w-full h-full object-cover">
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full">
        
        <!-- Search Section -->
        <div class="flex justify-center mb-12">
            <div class="search-bar flex items-center w-full max-w-2xl px-4 shadow-sm">
                <svg class="w-5 h-5 text-gray-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
                <input type="text" id="search-input" class="search-input w-full text-lg" placeholder="Search crypto (e.g., BTCUSDT, ETHUSDT)...">
                <button id="search-btn" class="ml-2 text-[var(--accent-color)] font-medium hover:bg-[var(--hover-bg)] px-4 py-1 rounded-full transition-colors">
                    Search
                </button>
            </div>
        </div>

        <!-- Dashboard Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Main Chart Card -->
            <div class="lg:col-span-2 google-card p-6">
                <div class="flex justify-between items-start mb-6">
                    <div>
                        <h2 class="text-2xl font-normal mb-1" id="crypto-title">BTCUSDT Market Analysis</h2>
                        <div class="flex items-baseline gap-3">
                            <span class="text-3xl font-medium" id="current-price">Loading...</span>
                            <span class="text-sm font-medium" id="price-change">--</span>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <span class="px-3 py-1 rounded-full bg-[var(--hover-bg)] text-sm font-medium">15m</span>
                        <span class="px-3 py-1 rounded-full bg-[var(--hover-bg)] text-sm font-medium text-gray-500">1h</span>
                        <span class="px-3 py-1 rounded-full bg-[var(--hover-bg)] text-sm font-medium text-gray-500">4h</span>
                    </div>
                </div>
                
                <div id="chart-container" class="w-full h-[400px]"></div>
                
                <div class="mt-4 flex items-center gap-2 text-sm text-gray-500">
                    <span class="w-3 h-3 rounded-sm bg-[#FFA500]"></span>
                    <span>Orange Candle indicates AI Prediction (15m ahead)</span>
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
        </div>

    </main>

    <!-- App Script -->
    <script src="assets/js/app.js"></script>
</body>
</html>
