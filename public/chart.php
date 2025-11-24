<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crypto Intelligence - Live Chart</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- TradingView Lightweight Charts -->
    <script src="https://unpkg.com/lightweight-charts@4.1.1/dist/lightweight-charts.standalone.production.js"></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
</head>
<body class="min-h-screen flex flex-col bg-[var(--bg-color)] text-[var(--text-color)] font-sans">

    <!-- Header -->
    <header class="h-16 border-b border-[var(--border-color)] bg-[var(--card-bg)] sticky top-0 z-50 px-4 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <div class="flex items-center gap-2 cursor-pointer" onclick="window.location.href='index.php'">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-bold text-lg">C</div>
                <span class="text-lg font-bold tracking-tight">CryptoIntel</span>
            </div>
            
            <div class="h-6 w-px bg-[var(--border-color)] mx-2"></div>
            
            <nav class="hidden md:flex gap-1">
                <a href="index.php" class="btn-ghost text-sm">Dashboard</a>
                <a href="chart.php" class="btn-ghost text-sm text-[var(--accent-color)] bg-[var(--hover-bg)]">Markets</a>
                <a href="#" class="btn-ghost text-sm">Trade</a>
            </nav>
        </div>

        <div class="flex items-center gap-4">
            <!-- Search -->
            <div class="relative hidden md:block w-64">
                <svg class="search-icon w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                <input type="text" id="search-input" class="search-input py-1.5 text-sm" placeholder="Search coin..." autocomplete="off">
                <div id="search-widget" class="absolute top-full left-0 w-full mt-1 bg-[var(--card-bg)] border border-[var(--border-color)] rounded-lg shadow-lg hidden overflow-hidden z-50"></div>
            </div>

            <div class="theme-toggle p-2 rounded-lg hover:bg-[var(--hover-bg)] cursor-pointer" id="theme-toggle">
                <!-- Icon -->
            </div>
            
            <div class="w-8 h-8 rounded-full bg-gradient-to-r from-gray-700 to-gray-900 overflow-hidden border border-[var(--border-color)]">
                <img src="https://ui-avatars.com/api/?name=User&background=random" alt="User" class="w-full h-full object-cover">
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow flex flex-col md:flex-row h-[calc(100vh-64px)] overflow-hidden">
        
        <!-- Left: Chart Area -->
        <div class="flex-grow flex flex-col border-r border-[var(--border-color)] bg-[var(--bg-color)]">
            
            <!-- Chart Header -->
            <div class="h-14 border-b border-[var(--border-color)] bg-[var(--card-bg)] flex items-center justify-between px-4">
                <div class="flex items-center gap-4">
                    <div>
                        <h1 class="text-lg font-bold flex items-center gap-2" id="crypto-title">
                            BTC/USDT 
                            <span class="text-xs font-normal text-[var(--text-secondary)] px-1.5 py-0.5 rounded bg-[var(--hover-bg)]">PERP</span>
                        </h1>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-lg font-bold leading-none" id="current-price">--</span>
                        <span class="text-xs font-medium" id="price-change">--</span>
                    </div>
                </div>

                <!-- Interval Selector -->
                <div class="flex bg-[var(--hover-bg)] rounded-lg p-1 gap-1" id="interval-selector">
                    <button class="interval-btn text-xs" data-interval="15m">15m</button>
                    <button class="interval-btn text-xs active" data-interval="1h">1h</button>
                    <button class="interval-btn text-xs" data-interval="4h">4H</button>
                    <button class="interval-btn text-xs" data-interval="1d">1D</button>
                    <button class="interval-btn text-xs" data-interval="1w">1W</button>
                </div>
            </div>

            <!-- Chart Container -->
            <div class="flex-grow relative bg-[var(--card-bg)]">
                <div id="chart-container" class="w-full h-full"></div>
                
                <!-- AI Overlay -->
                <div class="absolute top-4 left-4 bg-[var(--card-bg)]/90 backdrop-blur-sm border border-[var(--border-color)] rounded-lg p-3 shadow-lg z-10">
                    <div class="text-xs text-[var(--text-secondary)] uppercase font-bold mb-1">AI Prediction (15m)</div>
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full bg-orange-500 animate-pulse"></div>
                        <span class="text-sm font-bold text-orange-500">Processing...</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Sidebar (Order Book / Trades) -->
        <div class="w-full md:w-80 flex flex-col bg-[var(--card-bg)]">
            
            <!-- Tabs -->
            <div class="flex border-b border-[var(--border-color)]">
                <button class="flex-1 py-3 text-sm font-medium border-b-2 border-[var(--accent-color)] text-[var(--text-color)]">Market Trades</button>
                <button class="flex-1 py-3 text-sm font-medium text-[var(--text-secondary)] hover:text-[var(--text-color)]">Order Book</button>
            </div>

            <!-- Trades List -->
            <div class="flex-grow overflow-y-auto p-0">
                <table class="w-full text-left text-xs">
                    <thead class="sticky top-0 bg-[var(--card-bg)] z-10">
                        <tr class="text-[var(--text-secondary)]">
                            <th class="py-2 px-4 font-normal">Price(USDT)</th>
                            <th class="py-2 px-4 font-normal text-right">Amount</th>
                            <th class="py-2 px-4 font-normal text-right">Time</th>
                        </tr>
                    </thead>
                    <tbody id="trades-body">
                        <!-- Simulated Trades -->
                        <tr class="hover:bg-[var(--hover-bg)] cursor-pointer">
                            <td class="py-1.5 px-4 text-[var(--danger-color)]">96,450.50</td>
                            <td class="py-1.5 px-4 text-right">0.0045</td>
                            <td class="py-1.5 px-4 text-right text-[var(--text-secondary)]">14:32:01</td>
                        </tr>
                        <tr class="hover:bg-[var(--hover-bg)] cursor-pointer">
                            <td class="py-1.5 px-4 text-[var(--success-color)]">96,451.00</td>
                            <td class="py-1.5 px-4 text-right">0.1200</td>
                            <td class="py-1.5 px-4 text-right text-[var(--text-secondary)]">14:32:05</td>
                        </tr>
                        <!-- More rows will be injected by JS -->
                    </tbody>
                </table>
            </div>

            <!-- Bottom Panel: User Assets -->
            <div class="p-4 border-t border-[var(--border-color)] bg-[var(--hover-bg)]">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-xs text-[var(--text-secondary)]">Available Balance</span>
                    <span class="text-xs text-[var(--accent-color)] cursor-pointer">Deposit</span>
                </div>
                <div class="text-lg font-bold">1,000.00 USDT</div>
            </div>
        </div>

    </main>

    <!-- App Script -->
    <script src="assets/js/app.js"></script>
</body>
</html>
