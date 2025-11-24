document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('search-input');
    const searchBtn = document.getElementById('search-btn');
    const themeToggle = document.getElementById('theme-toggle');
    const chartContainer = document.getElementById('chart-container');
    const currentPriceEl = document.getElementById('current-price');
    const priceChangeEl = document.getElementById('price-change');
    const cryptoTitle = document.getElementById('crypto-title');

    let chart;
    let candleSeries;
    let predictionSeries;
    let currentSymbol = 'BTCUSDT';

    // Theme Toggle
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);

    if (themeToggle) {
        updateThemeIcon(savedTheme);
        themeToggle.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
            if (chart) {
                applyChartTheme(newTheme);
            }
        });
    }

    function updateThemeIcon(theme) {
        if (!themeToggle) return;
        themeToggle.innerHTML = theme === 'light'
            ? '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>'
            : '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>';
    }

    // Initialize Chart
    function initChart() {
        if (!chartContainer) return;

        const chartOptions = {
            layout: {
                background: { type: 'solid', color: 'transparent' },
                textColor: getComputedStyle(document.documentElement).getPropertyValue('--text-color').trim(),
            },
            grid: {
                vertLines: { color: 'rgba(197, 203, 206, 0.2)' },
                horzLines: { color: 'rgba(197, 203, 206, 0.2)' },
            },
            timeScale: {
                timeVisible: true,
                secondsVisible: false,
            },
        };

        chart = LightweightCharts.createChart(chartContainer, chartOptions);

        candleSeries = chart.addCandlestickSeries({
            upColor: '#26a69a',
            downColor: '#ef5350',
            borderVisible: false,
            wickUpColor: '#26a69a',
            wickDownColor: '#ef5350',
        });

        // Prediction Series (Orange)
        predictionSeries = chart.addCandlestickSeries({
            upColor: '#FFA500', // Orange
            downColor: '#FF8C00', // Darker Orange
            borderVisible: true,
            borderColor: '#FFA500',
            wickUpColor: '#FFA500',
            wickDownColor: '#FFA500',
        });

        applyChartTheme(document.documentElement.getAttribute('data-theme'));

        // Handle resize
        new ResizeObserver(entries => {
            if (entries.length === 0 || entries[0].target !== chartContainer) { return; }
            const newRect = entries[0].contentRect;
            chart.applyOptions({ height: newRect.height, width: newRect.width });
        }).observe(chartContainer);
    }

    function applyChartTheme(theme) {
        if (!chart) return;
        const textColor = theme === 'dark' ? '#e8eaed' : '#202124';
        const gridColor = theme === 'dark' ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';

        chart.applyOptions({
            layout: { textColor: textColor },
            grid: {
                vertLines: { color: gridColor },
                horzLines: { color: gridColor },
            }
        });
    }

    let currentInterval = '1h';

    // Interval Selector Logic
    const intervalBtns = document.querySelectorAll('.interval-btn');
    intervalBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            // Update UI
            intervalBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            // Update State & Fetch
            currentInterval = btn.getAttribute('data-interval');
            fetchData(currentSymbol, currentInterval);
        });
    });

    // Fetch Data
    async function fetchData(symbol, interval = '1h') {
        try {
            const response = await fetch(`api/market_data.php?symbol=${symbol}&interval=${interval}`);
            const data = await response.json();
            console.log("Fetched Data:", data); // Debug Log

            if (data.error) {
                console.error(data.error);
                return;
            }

            // Update Header
            if (cryptoTitle) cryptoTitle.textContent = `${data.symbol} Market Analysis`;
            const lastCandle = data.candles[data.candles.length - 1];

            // Dynamic Precision for Low-Value Coins (e.g. PEPE)
            const price = lastCandle.close;
            const precision = price < 1.0 ? 8 : 2;
            const minMove = price < 1.0 ? 0.00000001 : 0.01;

            if (currentPriceEl) currentPriceEl.textContent = `$${price.toFixed(precision)}`;

            // Calculate change (simple vs prev candle)
            const prevCandle = data.candles[data.candles.length - 2];
            const change = lastCandle.close - prevCandle.close;
            const changePercent = (change / prevCandle.close) * 100;
            if (priceChangeEl) {
                priceChangeEl.textContent = `${change >= 0 ? '+' : ''}${change.toFixed(precision)} (${changePercent.toFixed(2)}%)`;
                priceChangeEl.className = change >= 0 ? 'text-green-600 font-medium' : 'text-red-600 font-medium';
            }

            // Update Chart
            if (candleSeries) {
                // Apply Dynamic Precision
                candleSeries.applyOptions({
                    priceFormat: {
                        type: 'price',
                        precision: precision,
                        minMove: minMove,
                    },
                });
                predictionSeries.applyOptions({
                    priceFormat: {
                        type: 'price',
                        precision: precision,
                        minMove: minMove,
                    },
                });

                candleSeries.setData(data.candles);

                const markers = [];

                // Future Prediction Marker
                if (data.prediction) {
                    predictionSeries.setData([data.prediction]);
                    markers.push({
                        time: data.prediction.time,
                        position: 'aboveBar',
                        color: '#FFA500',
                        shape: 'arrowDown',
                        text: `Prediction (${data.prediction.confidence}%)`,
                    });
                } else {
                    predictionSeries.setData([]);
                }

                // Past Prediction Markers (Ticks/Crosses)
                if (data.past_predictions) {
                    data.past_predictions.forEach(p => {
                        markers.push({
                            time: p.time,
                            position: 'aboveBar',
                            color: p.is_correct ? '#4CAF50' : '#EF5350', // Green or Red
                            shape: 'circle',
                            text: p.is_correct ? '✔' : '✘',
                            size: 2
                        });
                    });
                }

                predictionSeries.setMarkers(markers);
                chart.timeScale().fitContent();
            }

        } catch (e) {
            console.error("Error fetching data:", e);
        }
    }

    // Report Button
    const reportBtn = document.querySelector('button.bg-\\[var\\(--accent-color\\)\\]');
    if (reportBtn) {
        reportBtn.addEventListener('click', async () => {
            try {
                const originalText = reportBtn.textContent;
                reportBtn.textContent = "Loading...";
                const response = await fetch('api/report.php');
                const report = await response.json();

                alert(`Daily Prediction Report (${report.date})\n\nTotal Predictions: ${report.total_predictions}\nCorrect: ${report.correct_predictions}\nWin Rate: ${report.win_rate}%\nStatus: ${report.status}`);

                reportBtn.textContent = originalText;
            } catch (e) {
                console.error("Error fetching report:", e);
                reportBtn.textContent = "Error";
            }
        });
    }

    function handleSearch() {
        if (!searchInput) return;
        const query = searchInput.value.toUpperCase().trim();
        if (query) {
            currentSymbol = query;
            fetchData(currentSymbol, currentInterval);
        }
    }

    if (searchBtn) {
        searchBtn.addEventListener('click', handleSearch);
    }

    if (searchInput) {
        searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') handleSearch();
        });

        // Search Widget Logic
        const searchWidget = document.getElementById('search-widget');
        if (searchWidget) {
            searchInput.addEventListener('input', async (e) => {
                const query = e.target.value.trim().toUpperCase();
                if (query.length < 1) {
                    searchWidget.classList.add('hidden');
                    return;
                }

                try {
                    const response = await fetch(`api/search.php?q=${query}`);
                    const suggestions = await response.json();

                    searchWidget.innerHTML = '';
                    searchWidget.classList.remove('hidden');

                    if (suggestions.length > 0) {
                        suggestions.forEach(symbol => {
                            const div = document.createElement('div');
                            div.className = 'search-item';
                            div.innerHTML = `
                                <div class="symbol">${symbol}</div>
                                <div class="name">Crypto Asset</div>
                            `;
                            div.addEventListener('click', () => {
                                searchInput.value = symbol;
                                searchWidget.classList.add('hidden');
                                // If on chart page, update chart. If on dashboard, redirect.
                                if (window.location.pathname.includes('chart.php')) {
                                    currentSymbol = symbol;
                                    fetchData(currentSymbol, currentInterval);
                                } else {
                                    window.location.href = `chart.php?symbol=${symbol}`;
                                }
                            });
                            searchWidget.appendChild(div);
                        });
                    }

                    // "Force Add" Option
                    const addDiv = document.createElement('div');
                    addDiv.className = 'search-item';
                    addDiv.style.borderTop = '1px solid var(--border-color)';
                    addDiv.innerHTML = `
                        <div class="symbol text-[var(--accent-color)]">Add "${query}"</div>
                        <div class="name">Fetch from Binance</div>
                    `;
                    addDiv.addEventListener('click', () => {
                        searchInput.value = query;
                        searchWidget.classList.add('hidden');
                        if (window.location.pathname.includes('chart.php')) {
                            currentSymbol = query;
                            fetchData(currentSymbol, currentInterval);
                        } else {
                            window.location.href = `chart.php?symbol=${query}`;
                        }
                    });
                    searchWidget.appendChild(addDiv);

                } catch (e) {
                    console.error("Error fetching suggestions:", e);
                }
            });

            // Hide widget when clicking outside
            document.addEventListener('click', (e) => {
                if (!searchInput.contains(e.target) && !searchWidget.contains(e.target)) {
                    searchWidget.classList.add('hidden');
                }
            });
        }
    }

    // Check URL params for symbol on load (for chart.php redirect)
    const urlParams = new URLSearchParams(window.location.search);
    const paramSymbol = urlParams.get('symbol');
    if (paramSymbol) {
        currentSymbol = paramSymbol;
        if (searchInput) searchInput.value = currentSymbol;
    }

    // Fetch Trades
    async function fetchTrades() {
        const tbody = document.getElementById('trades-body');
        if (!tbody) return;

        try {
            const response = await fetch('api/trades.php');
            const trades = await response.json();

            tbody.innerHTML = '';

            if (trades.error) return;

            trades.forEach(trade => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-[var(--hover-bg)] cursor-pointer transition-colors';

                const pnl = parseFloat(trade.pnl || 0);
                const isBuy = trade.type === 'BUY'; // Assuming 'type' exists or inferring
                const priceClass = isBuy ? 'text-[var(--success-color)]' : 'text-[var(--danger-color)]';

                // Randomize amount for demo if not present
                const amount = (Math.random() * 0.5).toFixed(4);
                const timeStr = new Date(trade.entry_time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });

                tr.innerHTML = `
                    <td class="py-1.5 px-4 ${priceClass} font-medium">${parseFloat(trade.entry_price).toFixed(2)}</td>
                    <td class="py-1.5 px-4 text-right text-[var(--text-color)]">${amount}</td>
                    <td class="py-1.5 px-4 text-right text-[var(--text-secondary)]">${timeStr}</td>
                `;
                tbody.appendChild(tr);
            });
        } catch (e) {
            console.error("Error fetching trades:", e);
        }
    }

    // Initial Load
    initChart();
    fetchData(currentSymbol, currentInterval);
    fetchTrades();

    // Auto refresh every 60s
    setInterval(() => {
        fetchData(currentSymbol, currentInterval);
        fetchTrades();
    }, 60000);
});
