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

    // Order Book Elements
    const obAsks = document.getElementById('orderbook-asks');
    const obBids = document.getElementById('orderbook-bids');
    const obPrice = document.getElementById('ob-price');

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

            const candle = {
                time: kline.t / 1000,
                open: parseFloat(kline.o),
                high: parseFloat(kline.h),
                low: parseFloat(kline.l),
                close: parseFloat(kline.c),
            };

            if (candleSeries) {
                candleSeries.update(candle);
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

            // Mock Suggestions for now or fetch from API
            // ... (Search logic similar to before)
        });
    }

    // Initial Load
    initChart();
    fetchData(currentSymbol, currentInterval);
    fetchWatchlist();

    // Poll for prediction updates every 15s (Dynamic updates)
    setInterval(() => {
        // Only fetch if tab is visible to save resources
        if (!document.hidden) {
            fetchData(currentSymbol, currentInterval);
            fetchWatchlist(); // Update watchlist too
        }
    }, 15000);

});
