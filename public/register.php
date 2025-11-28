<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account | CryptoIntel</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        bg: { DEFAULT: '#0b0e11', card: '#151a1f' },
                        text: { primary: '#eaecef', secondary: '#848e9c' },
                        accent: { blue: '#2962ff' }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-bg text-text-primary h-screen flex items-center justify-center">

    <div class="w-full max-w-md p-8 bg-bg-card rounded-2xl border border-gray-800 shadow-2xl">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-gradient-to-tr from-accent-blue to-purple-600 text-white font-bold text-xl mb-4 shadow-lg shadow-accent-blue/20">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
            </div>
            <h1 class="text-2xl font-bold">Create Account</h1>
            <p class="text-text-secondary mt-2">Get started with CryptoIntel</p>
        </div>

        <form id="register-form" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-text-secondary mb-1">Username</label>
                <input type="text" id="username" class="w-full px-4 py-2 bg-bg border border-gray-700 rounded-lg focus:outline-none focus:border-accent-blue focus:ring-1 focus:ring-accent-blue transition-colors" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-text-secondary mb-1">Email</label>
                <input type="email" id="email" class="w-full px-4 py-2 bg-bg border border-gray-700 rounded-lg focus:outline-none focus:border-accent-blue focus:ring-1 focus:ring-accent-blue transition-colors" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-text-secondary mb-1">Password</label>
                <input type="password" id="password" class="w-full px-4 py-2 bg-bg border border-gray-700 rounded-lg focus:outline-none focus:border-accent-blue focus:ring-1 focus:ring-accent-blue transition-colors" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-text-secondary mb-1">Confirm Password</label>
                <input type="password" id="confirm-password" class="w-full px-4 py-2 bg-bg border border-gray-700 rounded-lg focus:outline-none focus:border-accent-blue focus:ring-1 focus:ring-accent-blue transition-colors" required>
            </div>
            
            <div id="error-msg" class="text-red-500 text-sm hidden"></div>
            <div id="success-msg" class="text-green-500 text-sm hidden">Account created! Redirecting...</div>

            <button type="submit" class="w-full py-2.5 bg-accent-blue hover:bg-blue-600 text-white font-medium rounded-lg transition-colors">Create Account</button>
        </form>

        <div class="mt-6 text-center text-sm text-text-secondary">
            Already have an account? <a href="login.php" class="text-accent-blue hover:underline">Sign in</a>
        </div>
    </div>

    <script>
        document.getElementById('register-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const username = document.getElementById('username').value;
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm-password').value;
            const errorMsg = document.getElementById('error-msg');
            const successMsg = document.getElementById('success-msg');

            if (password !== confirmPassword) {
                errorMsg.textContent = 'Passwords do not match';
                errorMsg.classList.remove('hidden');
                return;
            }

            try {
                const response = await fetch('api/auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'register', username, email, password })
                });
                const data = await response.json();

                if (data.success) {
                    errorMsg.classList.add('hidden');
                    successMsg.classList.remove('hidden');
                    setTimeout(() => window.location.href = 'login.php', 1500);
                } else {
                    errorMsg.textContent = data.error;
                    errorMsg.classList.remove('hidden');
                }
            } catch (e) {
                errorMsg.textContent = 'An error occurred. Please try again.';
                errorMsg.classList.remove('hidden');
            }
        });
    </script>
</body>
</html>
