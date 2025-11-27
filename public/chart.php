<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crypto Intelligence | Pro Chart</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    },
                    colors: {
                        bg: {
                            DEFAULT: 'var(--bg-main)',
                            card: 'var(--bg-card)',
                            hover: 'var(--bg-hover)'
                        },
                        text: {
                            primary: 'var(--text-main)',
                            secondary: 'var(--text-secondary)',
                            muted: 'var(--text-muted)'
                        },
                        accent: {
                            blue: '#2962ff',
                            teal: '#0ecb81',
                            red: '#f6465d',
                            orange: '#f0b90b'
                        },
                        border: 'var(--border-color)'
                    }
                }
            }
        }
    </script>
    
    <!-- TradingView Lightweight Charts -->
    <script src="https://unpkg.com/lightweight-charts@4.1.1/dist/lightweight-charts.standalone.production.js"></script>
    
    <style>
        :root {
            /* Light Mode Variables */
            --bg-main: #ffffff;
            --bg-card: #f8f9fa;
            --bg-hover: #f0f1f2;
            --text-main: #1e2329;
            --text-secondary: #707a8a;
            --text-muted: #b7bdc6;
            --border-color: #eaecef;
        }

        .dark {
            /* Dark Mode Variables */
            --bg-main: #0b0e11;
            --bg-card: #151a1f;
            --bg-hover: #1e2329;
            --text-main: #eaecef;
            --text-secondary: #848e9c;
            --text-muted: #5e6673;
            --border-color: #2b3139;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: var(--bg-main); 
        }
        ::-webkit-scrollbar-thumb {
            background: var(--border-color); 
            border-radius: 3px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: var(--text-muted); 
        }
        
        .glass-panel {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
        }
    </style>
</head>
<body class="bg-bg text-text-primary h-screen flex flex-col overflow-hidden selection:bg-accent-blue selection:text-white">

    <!-- Navbar -->
    <header class="h-14 border-b border-border bg-bg-card flex items-center justify-between px-4 shrink-0 z-50">
        <div class="flex items-center gap-6">
            <!-- Logo -->
            <div class="flex items-center gap-2 cursor-pointer" onclick="window.location.href='index.php'">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-accent-blue to-purple-600 flex items-center justify-center text-white font-bold text-lg shadow-lg shadow-accent-blue/20">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                </div>
                <span class="text-lg font-bold tracking-tight bg-clip-text text-transparent bg-gradient-to-r from-white to-gray-400">CryptoIntel</span>
            </div>
            
            <!-- Navigation -->
            <nav class="hidden md:flex items-center gap-1">
                <a href="index.php" class="px-3 py-1.5 text-sm font-medium text-text-secondary hover:text-text-primary hover:bg-bg-hover rounded-md transition-colors">Dashboard</a>
                <a href="chart.php" class="px-3 py-1.5 text-sm font-medium text-accent-blue bg-accent-blue/10 rounded-md transition-colors">Markets</a>
                <a href="#" class="px-3 py-1.5 text-sm font-medium text-text-secondary hover:text-text-primary hover:bg-bg-hover rounded-md transition-colors">Trade</a>
                <a href="#" class="px-3 py-1.5 text-sm font-medium text-text-secondary hover:text-text-primary hover:bg-bg-hover rounded-md transition-colors">Analysis</a>
            </nav>
        </div>

        <div class="flex items-center gap-4">
            <!-- Search -->
            <div class="relative hidden md:block w-72">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-4 w-4 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <input type="text" id="search-input" class="block w-full pl-10 pr-3 py-1.5 border border-border rounded-lg leading-5 bg-bg text-text-primary placeholder-text-muted focus:outline-none focus:border-accent-blue focus:ring-1 focus:ring-accent-blue sm:text-sm transition-all" placeholder="Search Coin (e.g. BTC)" autocomplete="off">
                <div id="search-widget" class="absolute top-full left-0 w-full mt-1 bg-bg-card border border-border rounded-lg shadow-xl hidden overflow-hidden z-50"></div>
            </div>

            <!-- Theme Toggle -->
            <button id="theme-toggle" class="p-2 rounded-lg hover:bg-bg-hover text-text-secondary hover:text-text-primary transition-colors">
                <!-- Sun Icon (for dark mode) -->
                <svg class="w-5 h-5 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                <!-- Moon Icon (for light mode) -->
                <svg class="w-5 h-5 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
            </button>

            <!-- User -->
            <div class="h-8 w-8 rounded-full bg-gradient-to-r from-gray-700 to-gray-600 border border-border cursor-pointer hover:ring-2 hover:ring-accent-blue/50 transition-all"></div>
        </div>
    </header>

    <!-- Main Layout -->
    <main class="flex-1 flex overflow-hidden">
        
        <!-- Left Sidebar (Watchlist/Nav) - Collapsible on small screens -->
        <aside class="w-16 md:w-64 border-r border-border bg-bg flex flex-col hidden md:flex">
            <div class="p-3 border-b border-border">
                <h3 class="text-xs font-semibold text-text-secondary uppercase tracking-wider mb-2">Watchlist</h3>
                <div class="space-y-1">
                    <!-- JS Populated -->
                </div>
            </div>
            
            <div class="flex-1 p-3">
                <h3 class="text-xs font-semibold text-text-secondary uppercase tracking-wider mb-2">AI Insights</h3>
                <div class="p-3 rounded-lg bg-gradient-to-br from-accent-blue/10 to-transparent border border-accent-blue/20">
                    <div class="flex items-center gap-2 mb-1">
                        <div class="w-2 h-2 rounded-full bg-accent-blue animate-pulse"></div>
                        <span class="text-xs font-bold text-accent-blue">Market Sentiment</span>
                    </div>
                    <p class="text-xs text-text-secondary leading-relaxed">
                        Strong buy pressure detected on BTC 4H timeframe. RSI indicates potential breakout.
                    </p>
                </div>
            </div>
        </aside>

        <!-- Center: Chart Area -->
        <section class="flex-1 flex flex-col min-w-0 bg-bg">
            
            <!-- Ticker Bar -->
            <div class="h-16 border-b border-border bg-bg-card flex items-center justify-between px-4 shrink-0">
                <div class="flex items-center gap-6">
                    <div>
                        <h1 class="text-xl font-bold flex items-center gap-2 text-text-primary" id="crypto-title">
                            BTC/USDT
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-bg border border-border text-text-secondary">PERP</span>
                        </h1>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-lg font-mono font-medium text-accent-teal" id="current-price">--</span>
                        <span class="text-xs font-medium text-text-secondary" id="price-change">--</span>
                    </div>
                    
                    <div class="h-8 w-px bg-border mx-2 hidden md:block"></div>
                    
                    <div class="hidden md:flex gap-4 text-xs">
                        <div class="flex flex-col">
                            <span class="text-text-secondary">24h High</span>
                            <span class="font-mono text-text-primary">--</span>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-text-secondary">24h Low</span>
                            <span class="font-mono text-text-primary">--</span>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-text-secondary">24h Vol(USDT)</span>
                            <span class="font-mono text-text-primary">--</span>
                        </div>
                    </div>
                </div>

                <!-- Interval Selector -->
                <div class="flex bg-bg rounded-lg p-1 gap-0.5 border border-border" id="interval-selector">
                    <button class="interval-btn px-3 py-1 text-xs font-medium rounded hover:bg-bg-hover text-text-secondary transition-all" data-interval="15m">15m</button>
                    <button class="interval-btn px-3 py-1 text-xs font-medium rounded bg-bg-hover text-text-primary shadow-sm transition-all active" data-interval="1h">1H</button>
                    <button class="interval-btn px-3 py-1 text-xs font-medium rounded hover:bg-bg-hover text-text-secondary transition-all" data-interval="4h">4H</button>
                    <button class="interval-btn px-3 py-1 text-xs font-medium rounded hover:bg-bg-hover text-text-secondary transition-all" data-interval="1d">1D</button>
                    <button class="interval-btn px-3 py-1 text-xs font-medium rounded hover:bg-bg-hover text-text-secondary transition-all" data-interval="1w">1W</button>
                    <button class="interval-btn px-3 py-1 text-xs font-medium rounded hover:bg-bg-hover text-text-secondary transition-all" data-interval="1M">1M</button>
                </div>
            </div>

            <!-- Chart Canvas -->
            <div class="flex-1 relative">
                <div id="chart-container" class="w-full h-full"></div>
                
                <!-- Floating AI Prediction Panel -->
                <div class="absolute top-4 left-4 glass-panel rounded-xl p-4 shadow-2xl z-20 w-64 border-l-4 border-accent-orange transition-all duration-300 hover:scale-105">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <div class="text-[10px] text-text-secondary uppercase font-bold tracking-wider">AI Prediction</div>
                            <div class="text-xs font-bold text-accent-orange mt-0.5 flex items-center gap-1">
                                <span id="pred-interval">1H</span> FORECAST
                            </div>
                        </div>
                        <div class="px-2 py-0.5 rounded bg-accent-orange/10 text-accent-orange text-[10px] font-bold" id="pred-confidence">
                            --% CONF
                        </div>
                    </div>
                    
                    <div class="space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="text-xs text-text-secondary">Target</span>
                            <span class="text-sm font-mono font-bold text-text-primary" id="pred-target">--</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-xs text-text-secondary">Direction</span>
                            <span class="text-xs font-bold" id="pred-direction">--</span>
                        </div>
                    </div>
                    
                    <div class="mt-3 pt-2 border-t border-border/50">
                        <div class="text-[10px] text-text-muted text-right" id="pred-time">Next Update: --</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Right Sidebar (Order Book / Trades) -->
        <aside class="w-72 border-l border-border bg-bg-card flex flex-col hidden lg:flex">
            <!-- Tabs -->
            <div class="flex border-b border-border">
                <button class="flex-1 py-3 text-xs font-bold border-b-2 border-accent-blue text-accent-blue transition-colors" id="tab-btn-trades">Trades</button>
                <button class="flex-1 py-3 text-xs font-bold text-text-secondary hover:text-text-primary transition-colors" id="tab-btn-orderbook">Order Book</button>
            </div>

            <!-- Content Container -->
            <div class="flex-1 overflow-hidden relative">
                
                <!-- Trades View (Active by default) -->
                <div id="view-trades" class="absolute inset-0 flex flex-col bg-bg-card z-10">
                    <div class="p-2 bg-bg border-b border-border">
                        <h4 class="text-[10px] font-bold text-text-secondary uppercase tracking-wider mb-1">Active Positions</h4>
                        <div id="active-trades-list" class="space-y-1">
                            <!-- JS Populated -->
                            <div class="text-xs text-text-muted text-center py-2">No active trades</div>
                        </div>
                    </div>
                    
                    <div class="flex-1 overflow-y-auto custom-scrollbar p-2">
                        <h4 class="text-[10px] font-bold text-text-secondary uppercase tracking-wider mb-2 sticky top-0 bg-bg-card py-1">Trade History</h4>
                        <div id="trade-history-list" class="space-y-2">
                            <!-- JS Populated -->
                        </div>
                    </div>
                </div>

                <!-- Order Book View (Hidden) -->
                <div id="view-orderbook" class="absolute inset-0 flex flex-col bg-bg-card hidden">
                    <!-- Asks -->
                    <div class="flex-1 overflow-hidden flex flex-col justify-end pb-1">
                        <table class="w-full text-left text-[11px]">
                            <tbody id="orderbook-asks"></tbody>
                        </table>
                    </div>
                    
                    <!-- Current Price -->
                    <div class="py-2 px-4 border-y border-border bg-bg flex items-center justify-center gap-2">
                        <span class="text-lg font-bold text-accent-teal" id="ob-price">--</span>
                        <svg class="w-4 h-4 text-accent-teal" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>
                    </div>

                    <!-- Bids -->
                    <div class="flex-1 overflow-hidden pt-1">
                        <table class="w-full text-left text-[11px]">
                            <tbody id="orderbook-bids"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- User Assets -->
            <div class="p-4 border-t border-border bg-bg">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-xs text-text-secondary">Available Assets</span>
                    <button class="text-[10px] bg-bg-hover px-2 py-0.5 rounded text-text-primary hover:bg-border transition-colors">Deposit</button>
                </div>
                <div class="flex items-baseline gap-1">
                    <span class="text-lg font-bold text-text-primary" id="user-balance">--</span>
                    <span class="text-xs text-text-secondary">USDT</span>
                </div>
            </div>
        </aside>

    </main>

    <script src="assets/js/app.js"></script>
</body>
</html>
