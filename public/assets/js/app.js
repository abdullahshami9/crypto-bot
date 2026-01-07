document.addEventListener('DOMContentLoaded', () => {
    // --- Global Elements ---
    const searchInput = document.getElementById('search-input');
    const searchWidget = document.getElementById('search-widget');
    const themeToggle = document.getElementById('theme-toggle');

    // --- Chart Page Elements ---
    const chartContainer = document.getElementById('chart-container');
    const currentPriceEl = document.getElementById('current-price');
    const priceChangeEl = document.getElementById('price-change');
    const cryptoTitle = document.getElementById('crypto-title');

    // Prediction Panel
    const predIntervalEl = document.getElementById('pred-interval');
    const predConfidenceEl = document.getElementById('pred-confidence');
    const predTargetEl = document.getElementById('pred-target');
    const predDirectionEl = document.getElementById('pred-direction');
    const predTimeEl = document.getElementById('pred-time');

    // Order Book & Trades
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

    // --- Dashboard Elements ---
    const dashBalance = document.getElementById('dash-balance');
    const dashPnl = document.getElementById('dash-pnl');
    const dashActiveCount = document.getElementById('dash-active-count');
    const dashWinrate = document.getElementById('dash-winrate');
    const dashTradesBody = document.getElementById('dash-trades-body');
    const dashHistoryBody = document.getElementById('dash-history-body');
    const dashSignalsBody = document.getElementById('dash-signals-body');
    const closeAllBtn = document.getElementById('close-all-btn');
    const toggleBotBtn = document.getElementById('toggle-bot-btn');
    const botStatusIndicator = document.getElementById('bot-status-indicator');

    // --- Analysis Page Elements ---
    const analysisSignalsTableBody = document.getElementById('analysis-signals-table-body');
    const analysisPredictionsTableBody = document.getElementById('analysis-predictions-table-body');

    // --- State ---
    let chart;
    let candleSeries;
    let predictionSeries;
    let successSeries;
    let currentSymbol = 'BTCUSDT';
    let currentInterval = '1h';
    let ws = null;
    let lastUpdateTime = 0;

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
            if (chart) applyChartTheme(newTheme);
        });
    }

    // --- Search Logic ---
    if (searchInput) {
        searchInput.addEventListener('input', async (e) => {
            const query = e.target.value.trim().toUpperCase();
            if (query.length < 1) {
                searchWidget.classList.add('hidden');
                return;
            }

            try {
                const response = await fetch(`api/search.php?q=${query}`);
                const results = await response.json();

                searchWidget.innerHTML = '';
                if (results.length > 0) {
                    searchWidget.classList.remove('hidden');
                    results.forEach(coin => {
                        const div = document.createElement('div');
                        div.className = 'p-2 hover:bg-bg-hover cursor-pointer flex justify-between items-center';
                        div.innerHTML = `
                            <span class="font-bold text-sm">${coin.symbol}</span>
                            <span class="text-xs ${coin.price_change_24h >= 0 ? 'text-accent-teal' : 'text-accent-red'}">${parseFloat(coin.price_change_24h).toFixed(2)}%</span>
                        `;
                        div.onclick = () => {
                            window.location.href = `chart.php?symbol=${coin.symbol}`;
                        };
                        searchWidget.appendChild(div);
                    });
                } else {
                    searchWidget.classList.add('hidden');
                }
            } catch (e) {
                console.error("Search error:", e);
            }
        });

        // Hide widget on outside click
        document.addEventListener('click', (e) => {
            if (!searchInput.contains(e.target) && !searchWidget.contains(e.target)) {
                searchWidget.classList.add('hidden');
            }
        });
    }

    // --- Chart Page Logic ---
    if (chartContainer) {
        initChart();

        // Check URL params for symbol
        const urlParams = new URLSearchParams(window.location.search);
        const symbolParam = urlParams.get('symbol');
        if (symbolParam) currentSymbol = symbolParam;

        fetchData(currentSymbol, currentInterval);

        // Interval Selector
        const intervalBtns = document.querySelectorAll('.interval-btn');
        intervalBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const newInterval = btn.getAttribute('data-interval');
                if (newInterval === currentInterval) return;
                intervalBtns.forEach(b => {
                    b.classList.remove('active', 'bg-bg-hover', 'text-text-primary', 'shadow-sm');
                    b.classList.add('text-text-secondary', 'hover:bg-bg-hover');
                });
                btn.classList.remove('text-text-secondary', 'hover:bg-bg-hover');
                btn.classList.add('active', 'bg-bg-hover', 'text-text-primary', 'shadow-sm');
                currentInterval = newInterval;
                fetchData(currentSymbol, currentInterval);
            });
        });

        // --- Focus Start ---
        function sendFocusHeartbeat() {
            if (!currentSymbol) return;
            fetch('api/set_focus.php', {
                method: 'POST', body: JSON.stringify({ symbol: currentSymbol })
            }).catch(e => console.error(e));
        }
        setInterval(sendFocusHeartbeat, 10000);
        // --- Focus End ---

        // Tabs
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
    }

    // --- Dashboard Logic ---
    if (dashBalance) {
        fetchDashboardData();
        fetchBotStatus();

        if (closeAllBtn) {
            closeAllBtn.addEventListener('click', async () => {
                if (!confirm("Are you sure you want to CLOSE ALL active trades?")) return;
                try {
                    const response = await fetch('api/trades.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'close_all' })
                    });
                    const res = await response.json();
                    if (res.success) {
                        alert(res.message);
                        fetchDashboardData();
                    } else {
                        alert("Error: " + res.error);
                    }
                } catch (e) { console.error(e); }
            });
        }

        if (toggleBotBtn) {
            toggleBotBtn.addEventListener('click', async () => {
                try {
                    const response = await fetch('api/bot_control.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'toggle' })
                    });
                    const res = await response.json();
                    updateBotUI(res.enabled);
                } catch (e) { console.error(e); }
            });
        }
    }

    // --- Analysis Page Logic ---
    if (analysisSignalsTableBody) {
        fetchAnalysisData();
    }

    // --- Daily Analysis Page Logic ---
    const dailyHighsContainer = document.getElementById('daily-highs-container');
    if (dailyHighsContainer) {
        fetchDailyAnalysis();
    }

    // --- Shared Logic (Watchlist, Trades Sidebar) ---
    fetchWatchlist();
    if (activeTradesList) fetchTradesSidebar();

    // --- Polling ---
    setInterval(() => {
        if (document.hidden) return;
        fetchWatchlist();
        if (activeTradesList) fetchTradesSidebar();
        if (dashBalance) fetchDashboardData();
        if (analysisSignalsTableBody) fetchAnalysisData();
    }, 3000);

    // --- Functions ---

    async function fetchBotStatus() {
        try {
            const response = await fetch('api/bot_control.php');
            const res = await response.json();
            updateBotUI(res.enabled);
        } catch (e) { console.error(e); }
    }

    function updateBotUI(enabled) {
        if (!toggleBotBtn || !botStatusIndicator) return;
        if (enabled) {
            toggleBotBtn.textContent = 'Stop Trading';
            toggleBotBtn.classList.remove('bg-accent-teal/10', 'text-accent-teal', 'hover:bg-accent-teal');
            toggleBotBtn.classList.add('bg-accent-red/10', 'text-accent-red', 'hover:bg-accent-red');
            botStatusIndicator.classList.remove('bg-accent-red');
            botStatusIndicator.classList.add('bg-accent-teal', 'animate-pulse');
        } else {
            toggleBotBtn.textContent = 'Start Trading';
            toggleBotBtn.classList.remove('bg-accent-red/10', 'text-accent-red', 'hover:bg-accent-red');
            toggleBotBtn.classList.add('bg-accent-teal/10', 'text-accent-teal', 'hover:bg-accent-teal');
            botStatusIndicator.classList.remove('bg-accent-teal', 'animate-pulse');
            botStatusIndicator.classList.add('bg-accent-red');
        }
    }

    async function fetchDashboardData() {
        try {
            const response = await fetch('api/trades.php');
            const data = await response.json();
            if (data.error) return;

            // Stats
            if (dashBalance) dashBalance.textContent = '$' + parseFloat(data.portfolio.balance).toLocaleString('en-US', { minimumFractionDigits: 2 });

            let totalPnl = 0;
            let wins = 0;
            if (data.history) {
                data.history.forEach(t => {
                    const pnl = parseFloat(t.pnl || 0);
                    totalPnl += pnl;
                    if (pnl > 0) wins++;
                });
            }
            if (dashPnl) {
                dashPnl.textContent = (totalPnl >= 0 ? '+' : '') + '$' + totalPnl.toFixed(2);
                dashPnl.className = `text-2xl font-mono font-bold ${totalPnl >= 0 ? 'text-accent-teal' : 'text-accent-red'}`;
            }
            if (dashActiveCount) dashActiveCount.textContent = data.active ? data.active.length : 0;
            if (dashWinrate && data.history.length > 0) {
                const wr = (wins / data.history.length) * 100;
                dashWinrate.textContent = wr.toFixed(1) + '%';
            }

            // Tables
            if (dashTradesBody) {
                dashTradesBody.innerHTML = '';
                if (data.active) {
                    data.active.forEach(trade => {
                        const pnl = parseFloat(trade.pnl_amount);
                        const row = document.createElement('tr');
                        row.className = 'hover:bg-bg-hover transition-colors';
                        row.innerHTML = `
                            <td class="px-4 py-3 font-bold">${trade.symbol}</td>
                            <td class="px-4 py-3"><span class="text-xs font-bold px-2 py-0.5 rounded ${trade.type === 'LONG' ? 'bg-accent-teal/10 text-accent-teal' : 'bg-accent-red/10 text-accent-red'}">${trade.type}</span></td>
                            <td class="px-4 py-3 font-mono">${parseFloat(trade.entry_price).toFixed(trade.entry_price < 1 ? 6 : 2)}</td>
                            <td class="px-4 py-3 font-mono">${parseFloat(trade.current_price).toFixed(trade.current_price < 1 ? 6 : 2)}</td>
                            <td class="px-4 py-3 font-mono font-bold ${pnl >= 0 ? 'text-accent-teal' : 'text-accent-red'}">${pnl >= 0 ? '+' : ''}${pnl.toFixed(2)}</td>
                            <td class="px-4 py-3 text-xs text-text-secondary max-w-xs truncate" title="${trade.reasoning}">${trade.reasoning || '-'}</td>
                            <td class="px-4 py-3 text-right">
                                <button onclick="closeTrade(${trade.id})" class="text-xs bg-accent-red/10 text-accent-red hover:bg-accent-red hover:text-white px-2 py-1 rounded transition-colors">Close</button>
                            </td>
                        `;
                        dashTradesBody.appendChild(row);
                    });
                }
            }

            if (dashHistoryBody) {
                dashHistoryBody.innerHTML = '';
                if (data.history) {
                    data.history.slice(0, 10).forEach(trade => {
                        const pnl = parseFloat(trade.pnl);
                        const row = document.createElement('tr');
                        row.className = 'hover:bg-bg-hover transition-colors';
                        row.innerHTML = `
                            <td class="px-4 py-3 font-bold">${trade.symbol}</td>
                            <td class="px-4 py-3 font-mono font-bold ${pnl >= 0 ? 'text-accent-teal' : 'text-accent-red'}">${pnl >= 0 ? '+' : ''}${pnl.toFixed(2)}</td>
                            <td class="px-4 py-3 text-xs text-text-secondary">${trade.exit_reason || '-'}</td>
                            <td class="px-4 py-3 text-xs text-text-muted">${new Date(trade.exit_time).toLocaleString()}</td>
                        `;
                        dashHistoryBody.appendChild(row);
                    });
                }
            }

            // Fetch signals for dashboard
            if (dashSignalsBody) {
                const sigRes = await fetch('api/analysis.php');
                const sigData = await sigRes.json();
                if (sigData.signals) {
                    dashSignalsBody.innerHTML = '';
                    sigData.signals.slice(0, 5).forEach(sig => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td class="px-4 py-3 font-bold">${sig.symbol}</td>
                            <td class="px-4 py-3"><span class="text-xs font-bold ${sig.signal_type === 'BUY' ? 'text-accent-teal' : 'text-accent-red'}">${sig.signal_type}</span></td>
                            <td class="px-4 py-3 font-mono">${sig.score}</td>
                            <td class="px-4 py-3 text-xs text-text-muted">${new Date(sig.created_at).toLocaleTimeString()}</td>
                        `;
                        dashSignalsBody.appendChild(row);
                    });
                }
            }

        } catch (e) { console.error(e); }
    }

    async function fetchAnalysisData() {
        try {
            const response = await fetch('api/analysis.php');
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const data = await response.json();

            if (data.error) throw new Error(data.error);

            if (analysisSignalsTableBody && data.signals) {
                if (data.signals.length === 0) {
                    analysisSignalsTableBody.innerHTML = '<tr><td colspan="5" class="px-6 py-8 text-center text-text-secondary">No signals available.</td></tr>';
                } else {
                    analysisSignalsTableBody.innerHTML = '';
                    data.signals.forEach(sig => {
                        const isBuy = sig.signal_type === 'BUY';
                        const accentColor = isBuy ? 'border-accent-teal' : 'border-accent-red';
                        const badgeBg = isBuy ? 'bg-accent-teal/10 text-accent-teal' : 'bg-accent-red/10 text-accent-red';

                        const div = document.createElement('div');
                        row.className = 'hover:bg-bg-hover transition-colors cursor-pointer group';
                        row.onclick = () => {
                            const detailRow = document.getElementById(uniqueId);
                            const icon = document.getElementById(`icon-${uniqueId}`);
                            if (detailRow.classList.contains('hidden')) {
                                detailRow.classList.remove('hidden');
                                icon.style.transform = 'rotate(180deg)';
                            } else {
                                detailRow.classList.add('hidden');
                                icon.style.transform = 'rotate(0deg)';
                            }
                        };

                        row.innerHTML = `
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-bg border border-border flex items-center justify-center font-bold text-sm">
                                        ${sig.symbol.substring(0, 1)}
                                    </div>
                                    <span class="font-bold">${sig.symbol}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2.5 py-1 rounded-md text-xs font-bold ${badgeBg}">
                                    ${sig.signal_type}
                                </span>
                            </td>
                            <td class="px-6 py-4 font-mono text-sm">${sig.score}</td>
                            <td class="px-6 py-4 text-xs text-text-secondary">${new Date(sig.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</td>
                            <td class="px-6 py-4 text-right">
                                <svg id="icon-${uniqueId}" class="w-5 h-5 text-text-secondary transition-transform duration-200 ml-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </td>
                        `;
                        analysisSignalsTableBody.appendChild(row);

                        // Detail Row
                        const detailRow = document.createElement('tr');
                        detailRow.id = uniqueId;
                        detailRow.className = 'hidden bg-bg-hover/30';
                        detailRow.innerHTML = `
                            <td colspan="5" class="px-6 py-6">
                                <div class="flex flex-col gap-4">
                                    <div>
                                        <h4 class="text-xs font-bold text-text-secondary uppercase tracking-wider mb-2">Rationale</h4>
                                        <p class="text-sm text-text-primary leading-relaxed">${sig.rationale}</p>
                                    </div>
                                    ${sig.llm_analysis ? `
                                    <div class="pt-4 border-t border-border">
                                        <h4 class="text-xs font-bold text-accent-blue uppercase tracking-wider mb-2 flex items-center gap-2">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                            AI Insight
                                        </h4>
                                        <p class="text-sm text-text-secondary italic">"${sig.llm_analysis}"</p>
                                    </div>` : ''}
                                    <div class="mt-2 text-right">
                                        <a href="chart.php?symbol=${sig.symbol}" class="inline-flex items-center gap-1 text-sm font-bold text-accent-blue hover:text-white transition-colors">
                                            Open Chart <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                        </a>
                                    </div>
                                </div>
                            </td>
                        `;
                        analysisSignalsTableBody.appendChild(detailRow);
                    });
                }
            }

            if (analysisPredictionsTableBody && data.predictions) {
                if (data.predictions.length === 0) {
                    analysisPredictionsTableBody.innerHTML = '<tr><td colspan="5" class="px-6 py-8 text-center text-text-secondary">No predictions available.</td></tr>';
                } else {
                    analysisPredictionsTableBody.innerHTML = '';
                    data.predictions.forEach(pred => {
                        const confidence = parseFloat(pred.confidence_score);
                        const isHighConf = confidence > 75;

                        const div = document.createElement('div');
                        div.className = `bg-bg-card border border-border rounded-xl p-5 hover:border-purple-500/50 hover:shadow-lg transition-all duration-300 relative overflow-hidden`;
                        div.innerHTML = `
                        <div class="absolute inset-0 bg-gradient-to-br from-purple-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                        
                        <div class="flex justify-between items-start mb-3 relative z-10">
                            <span class="font-bold text-md">${pred.symbol}</span>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-bg border border-border text-text-secondary uppercase tracking-wide">${pred.interval}</span>
                        </div>

                        <div class="flex items-end justify-between relative z-10">
                            <div>
                                <span class="text-xs text-text-secondary block mb-0.5">Target Price</span>
                                <span class="text-xl font-mono font-bold tracking-tight">${parseFloat(pred.predicted_close).toFixed(2)}</span>
                            </div>
                            <div class="text-right">
                                <span class="block text-[10px] text-text-secondary mb-1">Confidence</span>
                                <div class="relative w-12 h-12 flex items-center justify-center">
                                    <svg class="w-full h-full transform -rotate-90">
                                        <circle cx="24" cy="24" r="20" fill="none" stroke="currentColor" stroke-width="4" class="text-bg-hover"></circle>
                                        <circle cx="24" cy="24" r="20" fill="none" stroke="currentColor" stroke-width="4" class="${isHighConf ? 'text-purple-500' : 'text-accent-blue'}" stroke-dasharray="126" stroke-dashoffset="${126 - (126 * confidence / 100)}"></circle>
                                    </svg>
                                    <span class="absolute text-[10px] font-bold">${confidence}%</span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 pt-3 border-t border-border/50 text-[10px] text-text-muted text-right relative z-10">
                            Target: ${new Date(pred.prediction_time).toLocaleString()}
                        </div>
                    `;
                        analysisPredictionsTableBody.appendChild(row);
                    });
                }
            }

        } catch (e) {
            console.error("Analysis Fetch Error:", e);
            if (analysisSignalsTableBody) analysisSignalsTableBody.innerHTML = `<tr><td colspan="5" class="p-4 bg-accent-red/10 text-accent-red text-center">Error: ${e.message}</td></tr>`;
            if (analysisPredictionsTableBody) analysisPredictionsTableBody.innerHTML = `<tr><td colspan="5" class="p-4 bg-accent-red/10 text-accent-red text-center">Error: ${e.message}</td></tr>`;
        }
    }

    async function fetchDailyAnalysis() {
        const highsContainer = document.getElementById('daily-highs-container');
        const lowsContainer = document.getElementById('daily-lows-container');
        const insightsContainer = document.getElementById('daily-insights-container');

        try {
            const response = await fetch('api/daily_analysis.php');
            const data = await response.json();

            if (highsContainer && data.highs) {
                highsContainer.innerHTML = '';
                data.highs.forEach(coin => {
                    const card = createDailyCard(coin, 'high');
                    highsContainer.appendChild(card);
                });
            }

            if (lowsContainer && data.lows) {
                lowsContainer.innerHTML = '';
                data.lows.forEach(coin => {
                    const card = createDailyCard(coin, 'low');
                    lowsContainer.appendChild(card);
                });
            }

            if (insightsContainer && data.insights) {
                insightsContainer.innerHTML = '';
                data.insights.forEach(insight => {
                    const div = document.createElement('div');
                    div.className = 'bg-bg hover:bg-bg-hover border border-border rounded-xl p-4 flex gap-3 items-start transition-colors';
                    const iconColor = insight.type === 'hot' ? 'text-accent-red bg-accent-red/10' : 'text-accent-teal bg-accent-teal/10';
                    const icon = insight.type === 'hot'
                        ? '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>'
                        : '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>';

                    div.innerHTML = `
                        <div class="p-2 rounded-full shrink-0 ${iconColor}">
                            ${icon}
                        </div>
                        <div>
                            <span class="font-bold ml-1 ${insight.type === 'hot' ? 'text-accent-red' : 'text-accent-teal'}">${insight.title}</span>
                            <span class="text-text-secondary text-sm ml-1">${insight.text}</span>
                        </div>
                    `;
                    insightsContainer.appendChild(div);
                });
            }

        } catch (e) {
            console.error("Error fetching daily analysis:", e);
        }
    }

    function createDailyCard(coin, type) {
        const div = document.createElement('div');
        div.className = 'bg-bg text-text-primary p-6 rounded-xl border border-border hover:shadow-lg transition-all pt-10 relative mt-4'; // Added pt-10 for overlapping badge

        const badgeColor = type === 'high' ? 'bg-amber-100/10 text-amber-500' : 'bg-blue-100/10 text-blue-500';
        const badgeBg = type === 'high' ? '#fff7ed' : '#eff6ff'; // Light mode fallback
        const badgeText = type === 'high' ? '#f59e0b' : '#3b82f6';

        // Conditional styling for light/dark mode handled by CSS vars usually, but hardcoded here for speed as per mock
        const badgeClass = `absolute top-4 right-4 px-3 py-1 text-xs font-bold rounded-full ${badgeColor}`;

        const priceColor = type === 'high' ? 'text-accent-teal' : 'text-text-primary'; // Highs usually mean high price, but let's stick to design.
        // Actually, for ATH analysis: 
        // Highs: "Current Price: $709" (Green)
        // Lows: "Current Price: $3.20" (Normal/Bold)

        const actionBtnClass = type === 'high'
            ? 'bg-accent-red/90 hover:bg-accent-red text-white'
            : 'bg-accent-teal/10 text-accent-teal hover:bg-accent-teal hover:text-white';

        const analysisBg = type === 'high' ? 'bg-red-500/5 text-red-500' : 'bg-green-500/5 text-green-500';

        div.innerHTML = `
            <div class="${badgeClass}">${coin.badge}</div>
            
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-gray-700 flex items-center justify-center font-bold text-white text-lg">
                    ${coin.symbol[0]}
                </div>
                <div>
                    <h3 class="font-bold text-lg">${coin.symbol} - ${coin.name}</h3>
                </div>
            </div>

            <div class="space-y-3 mb-6">
                <div class="flex items-center gap-2 text-sm text-text-secondary">
                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                    Current Price: <span class="font-mono font-bold text-base ${type === 'high' ? 'text-accent-teal' : 'text-text-primary'}">$${coin.price}</span>
                </div>
                <div class="flex items-center gap-2 text-sm text-text-secondary">
                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                    ${coin.description}
                </div>
                <div class="flex items-center gap-2 text-sm mt-3">
                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                    Analysis: <span class="font-bold px-2 py-0.5 rounded ${analysisBg} text-xs uppercase tracking-wide">${coin.analysis}</span>
                </div>
            </div>

            <button class="w-full py-2.5 rounded-lg font-bold text-sm transition-colors ${actionBtnClass}">
                ${coin.action}
            </button>
        `;
        return div;
    }

    async function fetchTradesSidebar() {
        try {
            const response = await fetch('api/trades.php');
            const data = await response.json();
            if (data.error) return;

            if (userBalanceEl) userBalanceEl.textContent = parseFloat(data.portfolio.balance).toLocaleString('en-US', { minimumFractionDigits: 2 });

            activeTradesList.innerHTML = '';
            if (data.active && data.active.length > 0) {
                data.active.forEach(trade => {
                    const isLong = trade.type === 'LONG';
                    const color = isLong ? 'text-accent-teal' : 'text-accent-red';
                    const pnlPct = parseFloat(trade.pnl_pct);
                    const pnlAmt = parseFloat(trade.pnl_amount);
                    const pnlColor = pnlPct >= 0 ? 'text-accent-teal' : 'text-accent-red';

                    const div = document.createElement('div');
                    div.className = 'p-2 rounded bg-bg border border-border flex flex-col gap-1';
                    div.innerHTML = `
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-bold text-text-primary">${trade.symbol}</span>
                            <span class="text-[10px] font-bold ${color}">${trade.type}</span>
                        </div>
                        <div class="flex justify-between items-center text-[10px] text-text-secondary">
                            <span>Entry: ${parseFloat(trade.entry_price).toFixed(2)}</span>
                            <span>Qty: ${parseFloat(trade.quantity).toFixed(4)}</span>
                        </div>
                        <div class="flex justify-between items-center mt-1 border-t border-border/50 pt-1">
                            <span class="text-xs font-bold ${pnlColor}">${pnlPct >= 0 ? '+' : ''}${pnlPct.toFixed(2)}% ($${pnlAmt.toFixed(2)})</span>
                            <button onclick="closeTrade(${trade.id})" class="px-2 py-0.5 text-[10px] bg-accent-red/10 text-accent-red hover:bg-accent-red hover:text-white rounded transition-colors">Close</button>
                        </div>
                    `;
                    activeTradesList.appendChild(div);
                });
            } else {
                activeTradesList.innerHTML = '<div class="text-xs text-text-muted text-center py-2">No active trades</div>';
            }

            if (tradeHistoryList) {
                tradeHistoryList.innerHTML = '';
                if (data.history) {
                    data.history.forEach(trade => {
                        const pnl = parseFloat(trade.pnl);
                        const pnlColor = pnl >= 0 ? 'text-accent-teal' : 'text-accent-red';
                        const div = document.createElement('div');
                        div.className = 'flex items-center justify-between p-2 rounded hover:bg-bg-hover transition-colors border-b border-border/50';
                        div.innerHTML = `
                            <div class="flex flex-col">
                                <span class="text-xs font-medium text-text-primary">${trade.symbol}</span>
                                <span class="text-[10px] text-text-secondary">${trade.type}</span>
                            </div>
                            <div class="flex flex-col items-end">
                                <span class="text-xs font-bold ${pnlColor}">${pnl >= 0 ? '+' : ''}${pnl.toFixed(2)}</span>
                                <span class="text-[10px] text-text-muted">${new Date(trade.exit_time).toLocaleDateString()}</span>
                            </div>
                        `;
                        tradeHistoryList.appendChild(div);
                    });
                }
            }
        } catch (e) { console.error(e); }
    }

    // --- Chart Functions (Same as before) ---
    function initChart() {
        if (!chartContainer) return;
        const isDark = document.documentElement.classList.contains('dark');
        const themeColors = getThemeColors(isDark ? 'dark' : 'light');
        const chartOptions = {
            layout: { background: { type: 'solid', color: themeColors.bg }, textColor: themeColors.text },
            grid: { vertLines: { color: themeColors.grid }, horzLines: { color: colors = themeColors.grid } },
            timeScale: { timeVisible: true, secondsVisible: false, borderColor: themeColors.border },
            rightPriceScale: { borderColor: themeColors.border },
            crosshair: { mode: LightweightCharts.CrosshairMode.Normal },
        };
        chart = LightweightCharts.createChart(chartContainer, chartOptions);
        candleSeries = chart.addCandlestickSeries({
            upColor: '#0ecb81', downColor: '#f6465d', borderVisible: false, wickUpColor: '#0ecb81', wickDownColor: '#f6465d',
        });
        successSeries = chart.addCandlestickSeries({
            upColor: '#38b6ff', downColor: '#38b6ff', borderVisible: false, wickUpColor: '#38b6ff', wickDownColor: '#38b6ff',
        });
        predictionSeries = chart.addCandlestickSeries({
            upColor: 'rgba(240, 185, 11, 0.5)', downColor: 'rgba(240, 185, 11, 0.5)', borderVisible: true, borderColor: '#f0b90b', wickUpColor: '#f0b90b', wickDownColor: '#f0b90b',
        });
        new ResizeObserver(entries => {
            if (entries.length === 0 || entries[0].target !== chartContainer) return;
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

    async function fetchData(symbol, interval = '1h') {
        try {
            if (cryptoTitle) {
                cryptoTitle.innerHTML = `${symbol} <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-bg border border-border text-text-secondary">PERP</span>`;
            }
            const response = await fetch(`api/market_data.php?symbol=${symbol}&interval=${interval}`);
            sendFocusHeartbeat();
            const data = await response.json();
            if (data.error) return;


            if (candleSeries && data.candles) {
                candleSeries.setData(data.candles);
                if (successSeries) successSeries.setData([]); // Reset success overlay on load

                if (data.candles.length > 0) lastUpdateTime = data.candles[data.candles.length - 1].time;

                if (data.prediction) {
                    predictionSeries.setData([data.prediction]);
                    updatePredictionPanel(data.prediction, interval);
                    window.currentPrediction = data.prediction; // Store for realtime logic
                } else {
                    predictionSeries.setData([]);
                    resetPredictionPanel();
                    window.currentPrediction = null;
                }
                chart.timeScale().fitContent();
            }

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
            connectWebSocket(symbol, interval);
            generateOrderBook(data.candles[data.candles.length - 1].close);
        } catch (e) { console.error(e); }
    }

    function updatePredictionPanel(prediction, interval) {
        if (!predIntervalEl) return;
        predIntervalEl.textContent = interval.toUpperCase();
        if (predConfidenceEl) {
            predConfidenceEl.textContent = `${prediction.confidence}% CONF`;
            const colorClass = prediction.confidence > 70 ? 'text-accent-teal bg-accent-teal/10' : prediction.confidence > 50 ? 'text-accent-orange bg-accent-orange/10' : 'text-accent-red bg-accent-red/10';
            predConfidenceEl.className = `px-2 py-0.5 rounded text-[10px] font-bold ${colorClass}`;
        }
        if (predTargetEl) predTargetEl.textContent = prediction.close.toFixed(prediction.close < 1 ? 8 : 2);
        if (predDirectionEl) {
            const isBullish = prediction.close > prediction.open;
            predDirectionEl.textContent = isBullish ? 'LONG ↗' : 'SHORT ↘';
            predDirectionEl.className = `text-xs font-bold ${isBullish ? 'text-accent-teal' : 'text-accent-red'}`;
        }
        if (predTimeEl) predTimeEl.textContent = `Target: ${new Date(prediction.time * 1000).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
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
        for (let i = 5; i > 0; i--) {
            const p = price + (i * spread);
            const amt = (Math.random() * 2).toFixed(4);
            const row = document.createElement('tr');
            row.innerHTML = `<td class="py-1 px-4 text-accent-red">${p.toFixed(2)}</td><td class="py-1 px-4 text-right text-text-secondary">${amt}</td>`;
            obAsks.appendChild(row);
        }
        for (let i = 1; i <= 5; i++) {
            const p = price - (i * spread);
            const amt = (Math.random() * 2).toFixed(4);
            const row = document.createElement('tr');
            row.innerHTML = `<td class="py-1 px-4 text-accent-teal">${p.toFixed(2)}</td><td class="py-1 px-4 text-right text-text-secondary">${amt}</td>`;
            obBids.appendChild(row);
        }
        if (obPrice) obPrice.textContent = price.toFixed(2);
    }

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
                div.onclick = () => { currentSymbol = coin.symbol; fetchData(currentSymbol, currentInterval); };
                const change = parseFloat(coin.price_change_24h);
                const changeClass = change >= 0 ? 'text-accent-teal' : 'text-accent-red';
                const confidence = parseFloat(coin.confidence_score);
                let badge = '';
                if (confidence > 80) badge = `<span class="text-[10px] bg-accent-teal/10 text-accent-teal px-1 rounded ml-1">${confidence.toFixed(0)}%</span>`;
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
        } catch (e) { console.error("Error fetching watchlist:", e); }
    }

    function connectWebSocket(symbol, interval) {
        if (ws) ws.close();
        const wsInterval = interval;
        const wsSymbol = symbol.toLowerCase();
        const wsUrl = `wss://stream.binance.com:9443/ws/${wsSymbol}@kline_${wsInterval}`;
        ws = new WebSocket(wsUrl);
        ws.onmessage = (event) => {
            const message = JSON.parse(event.data);
            const kline = message.k;
            const candleTime = Math.floor(kline.t / 1000);
            if (candleTime < lastUpdateTime) return;
            const candle = { time: candleTime, open: parseFloat(kline.o), high: parseFloat(kline.h), low: parseFloat(kline.l), close: parseFloat(kline.c) };
            if (candleSeries) {
                try {
                    candleSeries.update(candle);
                    lastUpdateTime = candleTime;

                    if (window.currentPrediction && successSeries) {
                        const pred = window.currentPrediction;
                        const isLong = pred.close > pred.open;
                        const openP = parseFloat(candle.open);
                        const closeP = parseFloat(candle.close);
                        let successCandle = null;

                        if (isLong && closeP > openP) {
                            successCandle = { time: candleTime, open: openP, close: closeP, high: closeP, low: openP };
                        } else if (!isLong && closeP < openP) {
                            successCandle = { time: candleTime, open: openP, close: closeP, high: openP, low: closeP };
                        }

                        if (successCandle) successSeries.update(successCandle);
                        else successSeries.update({ time: candleTime, open: closeP, close: closeP, high: closeP, low: closeP }); // Invisible
                    }
                } catch (error) { console.warn('Chart update skipped:', error.message); }
            }
            const price = candle.close;
            const precision = price < 1.0 ? 8 : 2;
            if (currentPriceEl) currentPriceEl.textContent = price.toFixed(precision);
            if (obPrice) obPrice.textContent = price.toFixed(precision);
            document.title = `${price.toFixed(precision)} | ${symbol} | CryptoIntel`;
        };
    }

    // Global Close Trade
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
                if (activeTradesList) fetchTradesSidebar();
                if (dashBalance) fetchDashboardData();
            } else {
                alert('Error: ' + result.error);
            }
        } catch (e) { console.error("Error closing trade:", e); }
    };
});
