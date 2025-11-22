import requests
import time
import mysql.connector
from db_utils import get_db_connection
from datetime import datetime

BINANCE_TICKER_URL = "https://api.binance.com/api/v3/ticker/24hr"
BINANCE_KLINES_URL = "https://api.binance.com/api/v3/klines"

def fetch_market_data():
    try:
        response = requests.get(BINANCE_TICKER_URL)
        response.raise_for_status()
        return response.json()
    except requests.RequestException as e:
        print(f"Error fetching data from Binance: {e}")
        return []

def fetch_historical_candles(symbol, interval="1h", limit=100):
    """
    Fetches historical kline/candle data for a symbol.
    """
    params = {
        "symbol": symbol,
        "interval": interval,
        "limit": limit
    }
    try:
        response = requests.get(BINANCE_KLINES_URL, params=params)
        response.raise_for_status()
        return response.json()
    except requests.RequestException as e:
        print(f"Error fetching historical data for {symbol}: {e}")
        return []

def update_market_data(data):
    conn = get_db_connection()
    if not conn:
        return

    cursor = conn.cursor()
    
    # Filter for USDT pairs
    usdt_pairs = [item for item in data if item['symbol'].endswith('USDT')]
    
    print(f"Processing {len(usdt_pairs)} pairs...")

    for item in usdt_pairs:
        symbol = item['symbol']
        price = float(item['lastPrice'])
        volume = float(item['quoteVolume'])
        price_change = float(item['priceChangePercent'])
        
        # Upsert into coins table
        sql = """
        INSERT INTO coins (symbol, price, volume, price_change_24h, ath, atl)
        VALUES (%s, %s, %s, %s, %s, %s)
        ON DUPLICATE KEY UPDATE
            price = VALUES(price),
            volume = VALUES(volume),
            price_change_24h = VALUES(price_change_24h),
            ath = GREATEST(ath, VALUES(price)),
            atl = LEAST(atl, VALUES(price))
        """
        val = (symbol, price, volume, price_change, price, price)
        
        try:
            cursor.execute(sql, val)
        except Exception as e:
            print(f"Error updating {symbol}: {e}")

    conn.commit()
    cursor.close()
    conn.close()
    print("Market data updated.")

def update_historical_data(symbol, interval="1h"):
    conn = get_db_connection()
    if not conn:
        return

    cursor = conn.cursor()
    
    candles = fetch_historical_candles(symbol, interval)
    if not candles:
        conn.close()
        return

    print(f"Updating history for {symbol} ({len(candles)} candles)...")

    sql = """
    REPLACE INTO historical_candles (symbol, `interval`, open, high, low, close, volume, close_time)
    VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
    """

    for candle in candles:
        # Binance kline format:
        # [
        #   1499040000000,      // Open time
        #   "0.01634790",       // Open
        #   "0.80000000",       // High
        #   "0.01575800",       // Low
        #   "0.01577100",       // Close
        #   "148976.11427815",  // Volume
        #   1499644799999,      // Close time
        #   ...
        # ]
        
        # Convert timestamp to datetime
        close_time_ts = candle[6] / 1000
        close_time = datetime.fromtimestamp(close_time_ts)
        
        val = (
            symbol, 
            interval, 
            float(candle[1]), 
            float(candle[2]), 
            float(candle[3]), 
            float(candle[4]), 
            float(candle[5]), 
            close_time
        )
        
        try:
            cursor.execute(sql, val)
        except Exception as e:
            print(f"Error inserting candle for {symbol}: {e}")

    conn.commit()
    cursor.close()
    conn.close()

if __name__ == "__main__":
    while True:
        print("Fetching market ticker...")
        data = fetch_market_data()
        if data:
            update_market_data(data)
            
            # For MVP, fetch history for top 10 volume coins to save API limits/time
            # Sort by quoteVolume
            usdt_pairs = [item for item in data if item['symbol'].endswith('USDT')]
            top_pairs = sorted(usdt_pairs, key=lambda x: float(x['quoteVolume']), reverse=True)[:10]
            
            for item in top_pairs:
                update_historical_data(item['symbol'], "1h")
                time.sleep(1) # Avoid rate limits

        print("Sleeping for 60s...")
        time.sleep(60)
