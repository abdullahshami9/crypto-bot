import os

# Database Configuration
DB_HOST = 'localhost'
DB_USER = 'root'
DB_PASSWORD = '' # Default for XAMPP/local dev
DB_NAME = 'crypto_engine'

# Binance API Configuration
# Use environment variables or set directly here (be careful with real keys)
BINANCE_API_KEY = os.getenv('BINANCE_API_KEY', '')
BINANCE_SECRET_KEY = os.getenv('BINANCE_SECRET_KEY', '')
USE_TESTNET = True # Set to False for real trading

# Trading Configuration
INITIAL_CAPITAL = 1000.00
TRADE_AMOUNT_PCT = 0.10 # 10% of balance per trade
LEVERAGE = 1 # 1x for Spot, higher for Futures if implemented

# Risk Management
STOP_LOSS_PCT = 0.02 # 2%
TAKE_PROFIT_PCT = 0.05 # 5%
