<?php
require_once '../includes/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | CryptoIntel</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Roboto+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'], mono: ['Roboto Mono', 'monospace'] },
                    colors: {
                        bg: { DEFAULT: '#0b0e11', card: '#151a1f', hover: '#1e2329' },
                        text: { primary: '#eaecef', secondary: '#848e9c', muted: '#5e6673' },
                        accent: { teal: '#0ecb81', red: '#f6465d', yellow: '#f0b90b', blue: '#2962ff' },
                        border: '#2b3139'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-bg text-text-primary font-sans antialiased min-h-screen flex flex-col">

    <?php include 'includes/header.php'; ?>

    <main class="flex-1 p-6 max-w-[1600px] mx-auto w-full">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">User Management</h1>
            <div class="relative">
                <input type="text" id="user-search" placeholder="Search users..." class="bg-bg-card border border-border rounded-lg px-4 py-2 text-sm focus:outline-none focus:border-accent-blue w-64">
            </div>
        </div>

        <div class="bg-bg-card border border-border rounded-xl overflow-hidden shadow-lg">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-bg-hover text-text-secondary text-xs uppercase border-b border-border">
                            <th class="px-6 py-4 font-medium cursor-pointer hover:text-text-primary" onclick="sortUsers('username')">User</th>
                            <th class="px-6 py-4 font-medium text-right cursor-pointer hover:text-text-primary" onclick="sortUsers('balance')">Balance</th>
                            <th class="px-6 py-4 font-medium text-right cursor-pointer hover:text-text-primary" onclick="sortUsers('total_pnl')">Total PnL</th>
                            <th class="px-6 py-4 font-medium text-right cursor-pointer hover:text-text-primary" onclick="sortUsers('roi')">ROI</th>
                            <th class="px-6 py-4 font-medium text-right cursor-pointer hover:text-text-primary" onclick="sortUsers('win_rate')">Win Rate</th>
                            <th class="px-6 py-4 font-medium text-right cursor-pointer hover:text-text-primary" onclick="sortUsers('total_trades')">Trades</th>
                            <th class="px-6 py-4 font-medium text-right cursor-pointer hover:text-text-primary" onclick="sortUsers('trades_per_day')">Trades/Day</th>
                            <th class="px-6 py-4 font-medium text-right">Joined</th>
                        </tr>
                    </thead>
                    <tbody id="users-table-body" class="text-sm divide-y divide-border">
                        <!-- Rows populated by JS -->
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        let usersData = [];
        let sortCol = 'total_pnl';
        let sortAsc = false;

        async function fetchUsers() {
            try {
                const response = await fetch('api/admin/users.php');
                const data = await response.json();
                if (data.error) {
                    console.error(data.error);
                    return;
                }
                usersData = data;
                renderTable();
            } catch (e) {
                console.error("Error fetching users:", e);
            }
        }

        function renderTable() {
            const tbody = document.getElementById('users-table-body');
            const search = document.getElementById('user-search').value.toLowerCase();
            
            let filtered = usersData.filter(u => 
                u.username.toLowerCase().includes(search) || 
                u.email.toLowerCase().includes(search)
            );

            filtered.sort((a, b) => {
                let valA = a[sortCol];
                let valB = b[sortCol];
                
                if (typeof valA === 'string') valA = valA.toLowerCase();
                if (typeof valB === 'string') valB = valB.toLowerCase();

                if (valA < valB) return sortAsc ? -1 : 1;
                if (valA > valB) return sortAsc ? 1 : -1;
                return 0;
            });

            tbody.innerHTML = '';
            filtered.forEach(user => {
                const pnl = parseFloat(user.total_pnl);
                const roi = parseFloat(user.roi);
                const pnlClass = pnl >= 0 ? 'text-accent-teal' : 'text-accent-red';
                
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-bg-hover transition-colors';
                tr.innerHTML = `
                    <td class="px-6 py-4">
                        <div class="flex flex-col">
                            <span class="font-bold text-text-primary">${user.username}</span>
                            <span class="text-xs text-text-secondary">${user.email}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-right font-mono">$${parseFloat(user.balance).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                    <td class="px-6 py-4 text-right font-mono font-bold ${pnlClass}">${pnl >= 0 ? '+' : ''}$${pnl.toFixed(2)}</td>
                    <td class="px-6 py-4 text-right font-mono font-bold ${pnlClass}">${roi >= 0 ? '+' : ''}${roi.toFixed(2)}%</td>
                    <td class="px-6 py-4 text-right font-mono">${parseFloat(user.win_rate).toFixed(1)}%</td>
                    <td class="px-6 py-4 text-right font-mono">${user.total_trades}</td>
                    <td class="px-6 py-4 text-right font-mono">${parseFloat(user.trades_per_day).toFixed(1)}</td>
                    <td class="px-6 py-4 text-right text-text-muted">${new Date(user.joined).toLocaleDateString()}</td>
                `;
                tbody.appendChild(tr);
            });
        }

        function sortUsers(col) {
            if (sortCol === col) {
                sortAsc = !sortAsc;
            } else {
                sortCol = col;
                sortAsc = false; // Default desc for new col
            }
            renderTable();
        }

        document.getElementById('user-search').addEventListener('input', renderTable);
        
        // Initial load
        fetchUsers();
    </script>
</body>
</html>
