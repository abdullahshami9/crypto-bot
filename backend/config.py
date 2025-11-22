import os

# Database Configuration
DB_HOST = 'localhost'
DB_USER = 'root'
DB_PASSWORD = '' # Default for XAMPP/local dev, change if needed
DB_NAME = 'crypto_engine'

# Binance API Configuration (Public endpoints don't strictly need keys for basic data, but good to have placeholders)
BINANCE_API_KEY = os.getenv('BINANCE_API_KEY', '')
BINANCE_SECRET_KEY = os.getenv('BINANCE_SECRET_KEY', '')

# Trading Configuration
INITIAL_CAPITAL = 1000.00
TRADE_AMOUNT = 100.00
