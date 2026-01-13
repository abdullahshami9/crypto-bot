<header class="h-14 border-b border-border bg-bg-card flex items-center justify-between px-4 shrink-0 z-50">
    <div class="flex items-center gap-6">
        <!-- Logo -->
        <div class="flex items-center gap-2 cursor-pointer" onclick="window.location.href='index.php'">
            <img src="assets/images/logo-dark-mode.png" alt="CryptoIntel" class="h-10 w-auto">
        </div>
        
        <!-- Navigation -->
        <nav class="hidden md:flex items-center gap-1">
            <?php if (isset($_SESSION['user_id'])): ?>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <a href="index.php" class="px-3 py-1.5 text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'text-accent-blue bg-accent-blue/10' : 'text-text-secondary hover:text-text-primary hover:bg-bg-hover'; ?> rounded-md transition-colors">Dashboard</a>
                    <a href="admin_users.php" class="px-3 py-1.5 text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'admin_users.php' ? 'text-accent-blue bg-accent-blue/10' : 'text-text-secondary hover:text-text-primary hover:bg-bg-hover'; ?> rounded-md transition-colors">Users</a>
                <?php endif; ?>
                <a href="chart.php" class="px-3 py-1.5 text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'chart.php' ? 'text-accent-blue bg-accent-blue/10' : 'text-text-secondary hover:text-text-primary hover:bg-bg-hover'; ?> rounded-md transition-colors">Markets</a>
                <a href="analysis.php" class="px-3 py-1.5 text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'analysis.php' ? 'text-accent-blue bg-accent-blue/10' : 'text-text-secondary hover:text-text-primary hover:bg-bg-hover'; ?> rounded-md transition-colors">Analysis</a>
                <a href="daily_analysis.php" class="px-3 py-1.5 text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'daily_analysis.php' ? 'text-accent-blue bg-accent-blue/10' : 'text-text-secondary hover:text-text-primary hover:bg-bg-hover'; ?> rounded-md transition-colors">Daily Analysis</a>
            <?php endif; ?>
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

        <!-- Add Coin Button -->
        <button id="add-coin-btn" class="hidden md:flex p-2 rounded-lg hover:bg-bg-hover text-text-secondary hover:text-text-primary transition-colors" title="Add New Coin">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
        </button>

        <!-- Sync Coin Button -->
        <button id="sync-coin-btn" class="hidden md:flex p-2 rounded-lg hover:bg-bg-hover text-text-secondary hover:text-text-primary transition-colors" title="Sync Data">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
        </button>

        <!-- Force Sync (Kraken) Button -->
        <button id="force-sync-btn" class="hidden md:flex p-2 rounded-lg hover:bg-red-500/10 text-red-500 hover:text-red-600 transition-colors" title="Force Sync (Kraken)">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
        </button>

        <!-- Theme Toggle -->
        <button id="theme-toggle" class="p-2 rounded-lg hover:bg-bg-hover text-text-secondary hover:text-text-primary transition-colors">
            <svg class="w-5 h-5 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
            <svg class="w-5 h-5 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
        </button>

        <!-- User -->
        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="flex items-center gap-3">
                <span class="text-sm font-medium text-text-secondary hidden md:block"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <div class="h-8 w-8 rounded-full bg-gradient-to-r from-gray-700 to-gray-600 border border-border cursor-pointer hover:ring-2 hover:ring-accent-blue/50 transition-all" onclick="logout()"></div>
            </div>
            <script>
                async function logout() {
                    await fetch('api/auth.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'logout' })
                    });
                    window.location.href = 'login.php';
                }
            </script>
        <?php else: ?>
            <a href="login.php" class="text-sm font-medium text-accent-blue hover:text-blue-400">Sign In</a>
        <?php endif; ?>
    </div>
</header>
