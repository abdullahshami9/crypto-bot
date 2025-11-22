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

    function updateThemeIcon(theme) {
        themeToggle.innerHTML = theme === 'light' 
            ? '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>'
            : '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>';
    }

    // Initialize Chart
    function initChart() {
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

    // Fetch Data
    async function fetchData(symbol) {
        try {
            const response = await fetch(`api/market_data.php?symbol=${symbol}`);
            const data = await response.json();
            
            if (data.error) {
                console.error(data.error);
                return;
            }

            // Update Header
            cryptoTitle.textContent = `${data.symbol} Market Analysis`;
            const lastCandle = data.candles[data.candles.length - 1];
            currentPriceEl.textContent = `$${lastCandle.close.toFixed(2)}`;
            
            // Calculate change (simple vs prev candle)
            const prevCandle = data.candles[data.candles.length - 2];
            const change = lastCandle.close - prevCandle.close;
            const changePercent = (change / prevCandle.close) * 100;
            
            priceChangeEl.textContent = `${change >= 0 ? '+' : ''}${change.toFixed(2)} (${changePercent.toFixed(2)}%)`;
            priceChangeEl.className = change >= 0 ? 'text-green-600 font-medium' : 'text-red-600 font-medium';

            // Update Chart
            candleSeries.setData(data.candles);
            
            if (data.prediction) {
                predictionSeries.setData([data.prediction]);
                
                // Add a marker for the prediction
                predictionSeries.setMarkers([
                    {
                        time: data.prediction.time,
                        position: 'aboveBar',
                        color: '#FFA500',
                        shape: 'arrowDown',
                        text: `Prediction (${data.prediction.confidence}%)`,
                    }
                ]);
            } else {
                predictionSeries.setData([]);
            }
            
            chart.timeScale().fitContent();

        } catch (e) {
            console.error("Error fetching data:", e);
        }
    }

    // Search Handler
    function handleSearch() {
        const query = searchInput.value.toUpperCase().trim();
        if (query) {
            currentSymbol = query;
            fetchData(currentSymbol);
        }
    }

    searchBtn.addEventListener('click', handleSearch);
    searchInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') handleSearch();
    });

    // Initial Load
    initChart();
    fetchData(currentSymbol);
    
    // Auto refresh every 60s
    setInterval(() => fetchData(currentSymbol), 60000);
});
