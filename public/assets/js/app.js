document.addEventListener('DOMContentLoaded', () => {
    // DOM Elements
    const searchInput = document.getElementById('search-input');
    const searchWidget = document.getElementById('search-widget');
    const chartContainer = document.getElementById('chart-container');
    const currentPriceEl = document.getElementById('current-price');
    const priceChangeEl = document.getElementById('price-change');
    const cryptoTitle = document.getElementById('crypto-title');
    const themeToggle = document.getElementById('theme-toggle');

    // Prediction Panel Elements
    const predIntervalEl = document.getElementById('pred-interval');
    const predConfidenceEl = document.getElementById('pred-confidence');
    const predTargetEl = document.getElementById('pred-target');
    const predDirectionEl = document.getElementById('pred-direction');
    const predTimeEl = document.getElementById('pred-time');

    // Order Book & Trades Elements
    const obAsks = document.getElementById('orderbook-asks');
    const obBids = document.getElementById('orderbook-bids');
    const obPrice = document.getElementById('ob-price');

    const tabBtnTrades = document.getElementById('tab-btn-trades');
    const tabBtnOrderbook = document.getElementById('tab-btn-orderbook');
    const viewTrades = document.getElementById('view-trades');
    const viewOrderbook = document.getElementById('view-orderbook');
    const activeTradesList = document.getElementById('active-trades-list');
    const tradeHistoryList = document.getElementById('trade-history-list');
    const userBalanceEl = document.getElementById('user-balance');

    let chart;
    let candleSeries;
    let predictionSeries;
    let currentSymbol = 'BTCUSDT';
    let currentInterval = '1h';
    let ws = null;

    // --- Theme Logic ---
    const savedTheme = localStorage.getItem('theme') || 'dark';
    document.documentElement.classList.toggle('dark', savedTheme === 'dark');
    document.documentElement.setAttribute('data-theme', savedTheme);

    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            const isDark = document.documentElement.classList.toggle('dark');
            const newTheme = isDark ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);

            if (chart) {
                applyChartTheme(newTheme);
            }
        });
    }

    // --- Tabs Logic ---
    if (tabBtnTrades && tabBtnOrderbook) {
        tabBtnTrades.addEventListener('click', () => {
            tabBtnTrades.classList.add('border-b-2', 'border-accent-blue', 'text-accent-blue');
            tabBtnTrades.classList.remove('text-text-secondary');
            tabBtnOrderbook.classList.remove('border-b-2', 'border-accent-blue', 'text-accent-blue');
            tabBtnOrderbook.classList.add('text-text-secondary');

            viewTrades.classList.remove('hidden');
            viewOrderbook.classList.add('hidden');
        });

        tabBtnOrderbook.addEventListener('click', () => {
            tabBtnOrderbook.classList.add('border-b-2', 'border-accent-blue', 'text-accent-blue');
            tabBtnOrderbook.classList.remove('text-text-secondary');
            tabBtnTrades.classList.remove('border-b-2', 'border-accent-blue', 'text-accent-blue');
            tabBtnTrades.classList.add('text-text-secondary');

            viewOrderbook.classList.remove('hidden');
            viewTrades.classList.add('hidden');
        });
    }

    // Initialize Chart
    function initChart() {
        if (!chartContainer) return;

        const isDark = document.documentElement.classList.contains('dark');
        const themeColors = getThemeColors(isDark ? 'dark' : 'light');

        const chartOptions = {
            layout: {
                background: { type: 'solid', color: themeColors.bg },
                textColor: themeColors.text,
            },
            grid: {
                vertLines: { color: themeColors.grid },
                horzLines: { color: themeColors.grid },
            },
            timeScale: {
                timeVisible: true,
                secondsVisible: false,
                borderColor: themeColors.border,
            },
            rightPriceScale: {
                borderColor: themeColors.border,
            },
            crosshair: {
                mode: LightweightCharts.CrosshairMode.Normal,
            },
        };

        chart = LightweightCharts.createChart(chartContainer, chartOptions);

        candleSeries = chart.addCandlestickSeries({
            upColor: '#0ecb81', // accent-teal
            downColor: '#f6465d', // accent-red
            borderVisible: false,
            wickUpColor: '#0ecb81',
            wickDownColor: '#f6465d',
        });

        // Prediction Series (Ghost Candles)
        predictionSeries = chart.addCandlestickSeries({
            upColor: 'rgba(240, 185, 11, 0.5)', // accent-orange transparent
            downColor: 'rgba(240, 185, 11, 0.5)',
            borderVisible: true,
            borderColor: '#f0b90b',
            wickUpColor: '#f0b90b',
            wickDownColor: '#f0b90b',
        });

        // Handle resize
        new ResizeObserver(entries => {
            if (entries.length === 0 || entries[0].target !== chartContainer) { return; }
            const newRect = entries[0].contentRect;
            chart.applyOptions({ height: newRect.height, width: newRect.width });
        }).observe(chartContainer);
    }

    function getThemeColors(theme) {
        const style = getComputedStyle(document.documentElement);
        return {
            bg: style.getPropertyValue('--bg-main').trim(),
            text: style.getPropertyValue('--text-main').trim(),
            grid: style.getPropertyValue('--border-color').trim(),
            border: style.getPropertyValue('--border-color').trim()
        };
    }

    function applyChartTheme(theme) {
        if (!chart) return;
        const colors = getThemeColors(theme);
        chart.applyOptions({
            layout: { background: { color: colors.bg }, textColor: colors.text },
            grid: { vertLines: { color: colors.grid }, horzLines: { color: colors.grid } },
            timeScale: { borderColor: colors.border },
            rightPriceScale: { borderColor: colors.border },
        });
    }

    // Interval Selector Logic
    const intervalBtns = document.querySelectorAll('.interval-btn');
    intervalBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const newInterval = btn.getAttribute('data-interval');
            if (newInterval === currentInterval) return;

            // Update UI
            intervalBtns.forEach(b => {
                b.classList.remove('active', 'bg-bg-hover', 'text-text-primary', 'shadow-sm');
                b.classList.add('text-text-secondary', 'hover:bg-bg-hover');
            });
            btn.classList.remove('text-text-secondary', 'hover:bg-bg-hover');
            btn.classList.add('active', 'bg-bg-hover', 'text-text-primary', 'shadow-sm');

            // Update State & Fetch
            currentInterval = newInterval;
            fetchData(currentSymbol, currentInterval);
        });
    });

    // Track last update time to prevent stale data
    let lastUpdateTime = 0;

    // WebSocket Connection
    function connectWebSocket(symbol, interval) {
        if (ws) ws.close();

        // Map 1M to 1M (Binance uses 1M)
        const wsInterval = interval;
        const wsSymbol = symbol.toLowerCase();
        const wsUrl = `wss://stream.binance.com:9443/ws/${wsSymbol}@kline_${wsInterval}`;

        console.log(`Connecting to WebSocket: ${wsUrl}`);
        ws = new WebSocket(wsUrl);

        ws.onmessage = (event) => {
            const message = JSON.parse(event.data);
            const kline = message.k;

            const candleTime = Math.floor(kline.t / 1000);

            // Only update if this is newer than or equal to our last update
            if (candleTime < lastUpdateTime) {
                return; // Skip stale data
            }

            const candle = {
                time: candleTime,
                open: parseFloat(kline.o),
                high: parseFloat(kline.h),
                low: parseFloat(kline.l),
                close: parseFloat(kline.c),
            };

            if (candleSeries) {
                try {
                    candleSeries.update(candle);
                    lastUpdateTime = candleTime;
                } catch (error) {
                    console.warn('Chart update skipped:', error.message);
                }
            }

            // Update Header Price
            const price = candle.close;
            const precision = price < 1.0 ? 8 : 2;
            if (currentPriceEl) currentPriceEl.textContent = price.toFixed(precision);
            if (obPrice) obPrice.textContent = price.toFixed(precision);

            // Update Title
            document.title = `${price.toFixed(precision)} | ${symbol} | CryptoIntel`;
        };
    }

    // Fetch Data
    async function fetchData(symbol, interval = '1h') {
        try {
            console.log(`Fetching data for ${symbol} ${interval}`);

            // Update Title UI
            if (cryptoTitle) {
                cryptoTitle.innerHTML = `
                    ${symbol}
                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-bg border border-border text-text-secondary">PERP</span>
                `;
            }

            const response = await fetch(`api/market_data.php?symbol=${symbol}&interval=${interval}`);
            const data = await response.json();

            if (data.error) {
                console.error(data.error);
                return;
            }

            // Update Chart Data
            if (candleSeries && data.candles) {
                candleSeries.setData(data.candles);

                // Update last update time to the most recent candle
                if (data.candles.length > 0) {
                    const lastCandle = data.candles[data.candles.length - 1];
                    lastUpdateTime = lastCandle.time;
                }

                // Update Prediction Series
                if (data.prediction) {
                    predictionSeries.setData([data.prediction]);
                    updatePredictionPanel(data.prediction, interval);
                } else {
                    predictionSeries.setData([]);
                    resetPredictionPanel();
                }

                // Fit Content
                chart.timeScale().fitContent();
            }

            // Update Header Stats (using last candle)
            if (data.candles && data.candles.length > 0) {
                const last = data.candles[data.candles.length - 1];
                const prev = data.candles.length > 1 ? data.candles[data.candles.length - 2] : last;

                const change = last.close - prev.close;
                const changePercent = (change / prev.close) * 100;

                if (priceChangeEl) {
                    priceChangeEl.textContent = `${change >= 0 ? '+' : ''}${changePercent.toFixed(2)}%`;
                    priceChangeEl.className = `text-xs font-medium ${change >= 0 ? 'text-accent-teal' : 'text-accent-red'}`;
                }
                if (currentPriceEl) {
                    currentPriceEl.textContent = last.close.toFixed(last.close < 1 ? 8 : 2);
                    currentPriceEl.className = `text-lg font-mono font-medium ${change >= 0 ? 'text-accent-teal' : 'text-accent-red'}`;
                }
            }

            // Connect WS
            connectWebSocket(symbol, interval);

            // Generate Orderbook
            generateOrderBook(data.candles[data.candles.length - 1].close);

        } catch (e) {
            console.error("Error fetching data:", e);
        }
    }

    function updatePredictionPanel(prediction, interval) {
        if (!predIntervalEl) return;

        predIntervalEl.textContent = interval.toUpperCase();

        if (predConfidenceEl) {
            predConfidenceEl.textContent = `${prediction.confidence}% CONF`;
            // Color based on confidence
            const colorClass = prediction.confidence > 70 ? 'text-accent-teal bg-accent-teal/10' :
                prediction.confidence > 50 ? 'text-accent-orange bg-accent-orange/10' :
                    'text-accent-red bg-accent-red/10';
            predConfidenceEl.className = `px-2 py-0.5 rounded text-[10px] font-bold ${colorClass}`;
        }

        if (predTargetEl) {
            const precision = prediction.close < 1 ? 8 : 2;
            predTargetEl.textContent = prediction.close.toFixed(precision);
        }

        if (predDirectionEl) {
            const isBullish = prediction.close > prediction.open;
            predDirectionEl.textContent = isBullish ? 'LONG ↗' : 'SHORT ↘';
            predDirectionEl.className = `text-xs font-bold ${isBullish ? 'text-accent-teal' : 'text-accent-red'}`;
        }

        if (predTimeEl) {
            const date = new Date(prediction.time * 1000);
            predTimeEl.textContent = `Target: ${date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
        }
    }

    function resetPredictionPanel() {
        if (predConfidenceEl) predConfidenceEl.textContent = '--%';
        if (predTargetEl) predTargetEl.textContent = '--';
        if (predDirectionEl) predDirectionEl.textContent = '--';
    }

    function generateOrderBook(price) {
        if (!obAsks || !obBids) return;

        obAsks.innerHTML = '';
        obBids.innerHTML = '';

        const spread = price * 0.0001;

        // Asks (Red)
        for (let i = 5; i > 0; i--) {
            const p = price + (i * spread);
            const amt = (Math.random() * 2).toFixed(4);
            const row = document.createElement('tr');
            row.innerHTML = `
                <td class="py-1 px-4 text-accent-red">${p.toFixed(2)}</td>
                <td class="py-1 px-4 text-right text-text-secondary">${amt}</td>
            `;
            obAsks.appendChild(row);
        }

        // Bids (Green)
        for (let i = 1; i <= 5; i++) {
            const p = price - (i * spread);
            const amt = (Math.random() * 2).toFixed(4);
            const row = document.createElement('tr');
            row.innerHTML = `
                <td class="py-1 px-4 text-accent-teal">${p.toFixed(2)}</td>
                <td class="py-1 px-4 text-right text-text-secondary">${amt}</td>
            `;
            obBids.appendChild(row);
        }

        if (obPrice) obPrice.textContent = price.toFixed(2);
    }

    // --- Watchlist Logic ---
    async function fetchWatchlist() {
        const watchlistContainer = document.querySelector('aside .space-y-1');
        if (!watchlistContainer) return;

        try {
            const response = await fetch('api/watchlist.php');
            const coins = await response.json();

            if (coins.error) return;

            watchlistContainer.innerHTML = '';

            coins.forEach(coin => {
                const div = document.createElement('div');
                div.className = 'flex items-center justify-between p-2 rounded-md hover:bg-bg-hover cursor-pointer group transition-colors';
                div.onclick = () => {
                    currentSymbol = coin.symbol;
                    fetchData(currentSymbol, currentInterval);
                };

                const change = parseFloat(coin.price_change_24h);
                const changeClass = change >= 0 ? 'text-accent-teal' : 'text-accent-red';
                const confidence = parseFloat(coin.confidence_score);

                // Show confidence badge if high
                let badge = '';
                if (confidence > 80) {
                    badge = `<span class="text-[10px] bg-accent-teal/10 text-accent-teal px-1 rounded ml-1">${confidence.toFixed(0)}%</span>`;
                }

                div.innerHTML = `
                    <div class="flex items-center gap-2">
                        <div class="w-5 h-5 rounded-full bg-gray-700 flex items-center justify-center text-[8px] font-bold text-white">${coin.symbol.substring(0, 1)}</div>
                        <span class="text-sm font-medium group-hover:text-accent-blue transition-colors">${coin.symbol}</span>
                        ${badge}
                    </div>
                    <span class="text-xs ${changeClass}">${change >= 0 ? '+' : ''}${change.toFixed(2)}%</span>
                `;
                watchlistContainer.appendChild(div);
            });

        } catch (e) {
            console.error("Error fetching watchlist:", e);
        }
    }

    // --- Trades Logic ---
    async function fetchTrades() {
        if (!activeTradesList || !tradeHistoryList) return;

        try {
            const response = await fetch('api/trades.php');
            const data = await response.json();
            console.log('Trades Data:', data);

            if (data.error) {
                console.error('API Error:', data.error);
                return;
            }

            // Update Balance
            if (data.portfolio && userBalanceEl) {
                userBalanceEl.textContent = parseFloat(data.portfolio.balance).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            // Active Trades
            activeTradesList.innerHTML = '';
            if (data.active && data.active.length > 0) {
                data.active.forEach(trade => {
                    const isLong = trade.type === 'LONG';
                    const color = isLong ? 'text-accent-teal' : 'text-accent-red';
                    const typeLabel = isLong ? 'LONG' : 'SHORT';

                    const pnlPct = parseFloat(trade.pnl_pct);
                    const pnlAmt = parseFloat(trade.pnl_amount);
                    const pnlColor = pnlPct >= 0 ? 'text-accent-teal' : 'text-accent-red';
                    const pnlSign = pnlPct >= 0 ? '+' : '';

                    const div = document.createElement('div');
                    div.className = 'p-2 rounded bg-bg border border-border flex flex-col gap-1';
                    div.innerHTML = `
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-bold text-text-primary">${trade.symbol}</span>
                            <span class="text-[10px] font-bold ${color}">${typeLabel}</span>
                        </div>
                        <div class="flex justify-between items-center text-[10px] text-text-secondary">
                            <span>Entry: ${parseFloat(trade.entry_price).toFixed(2)}</span>
                            <span>Qty: ${parseFloat(trade.quantity).toFixed(4)}</span>
                        </div>
                        <div class="flex justify-between items-center mt-1 border-t border-border/50 pt-1">
                            <span class="text-xs font-bold ${pnlColor}">${pnlSign}${pnlPct.toFixed(2)}% (${pnlSign}$${pnlAmt.toFixed(2)})</span>
                            <button onclick="closeTrade(${trade.id})" class="px-2 py-0.5 text-[10px] bg-accent-red/10 text-accent-red hover:bg-accent-red hover:text-white rounded transition-colors">Close</button>
                        </div>
                    `;
                    activeTradesList.appendChild(div);
                });
            } else {
                activeTradesList.innerHTML = '<div class="text-xs text-text-muted text-center py-2">No active trades</div>';
            }

            // Trade History
            tradeHistoryList.innerHTML = '';
            if (data.history && data.history.length > 0) {
                data.history.forEach(trade => {
                    const pnl = parseFloat(trade.pnl);
                    const isWin = pnl >= 0;
                    const pnlColor = isWin ? 'text-accent-teal' : 'text-accent-red';
                    const pnlSign = isWin ? '+' : '';

                    const div = document.createElement('div');
                    div.className = 'flex items-center justify-between p-2 rounded hover:bg-bg-hover transition-colors border-b border-border/50';
                    div.innerHTML = `
                        <div class="flex flex-col">
                            <span class="text-xs font-medium text-text-primary">${trade.symbol}</span>
                            <span class="text-[10px] text-text-secondary">${trade.type || 'TRADE'}</span>
                        </div>
                        <div class="flex flex-col items-end">
                            <span class="text-xs font-bold ${pnlColor}">${pnlSign}${pnl.toFixed(2)}</span>
                            <span class="text-[10px] text-text-muted">${new Date(trade.exit_time).toLocaleDateString()}</span>
                        </div>
                    `;
                    tradeHistoryList.appendChild(div);
                });
            }

        } catch (e) {
            console.error("Error fetching trades:", e);
        }
    }

    // Close Trade Function (Global)
    window.closeTrade = async (tradeId) => {
        if (!confirm('Are you sure you want to close this trade?')) return;

        try {
            const response = await fetch('api/trades.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'close', trade_id: tradeId })
            });
            const result = await response.json();

            if (result.success) {
                fetchTrades(); // Refresh list
            } else {
                alert('Error: ' + result.error);
            }
        } catch (e) {
            console.error("Error closing trade:", e);
        }
    };

    // Search Logic
    function handleSearch() {
        if (!searchInput) return;
        const query = searchInput.value.toUpperCase().trim();
        if (query) {
            currentSymbol = query;
            fetchData(currentSymbol, currentInterval);
            searchWidget.classList.add('hidden');
        }
    }

    if (searchInput) {
        searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') handleSearch();
        });

        searchInput.addEventListener('input', async (e) => {
            const query = e.target.value.trim().toUpperCase();
            if (query.length < 1) {
                searchWidget.classList.add('hidden');
                return;
            }
        });
    }

    // Initial Load
    initChart();
    fetchData(currentSymbol, currentInterval);
    fetchWatchlist();
    fetchTrades();

    // Poll for watchlist and trades updates
    setInterval(() => {
        if (!document.hidden) {
            fetchWatchlist();
            fetchTrades();
        }
    }, 2000); // Poll trades/watchlist faster (2s)

    // Poll for new predictions/signals
    setInterval(() => {
        if (!document.hidden) {
            fetchData(currentSymbol, currentInterval);
        }
    }, 10000); // Refresh chart/predictions every 10s

});
