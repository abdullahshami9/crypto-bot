import requests
import time
import mysql.connector
from db_utils import get_db_connection
from datetime import datetime

BINANCE_TICKER_URL = "https://api.binance.com/api/v3/ticker/24hr"
BINANCE_KLINES_URL = "https://api.binance.com/api/v3/klines"

def fetch_market_data():
    retries = 3
    for i in range(retries):
        try:
            response = requests.get(BINANCE_TICKER_URL, timeout=10)
            response.raise_for_status()
            return response.json()
        except requests.RequestException as e:
            print(f"Error fetching data from Binance (Attempt {i+1}/{retries}): {e}")
            time.sleep(2 * (i + 1)) # Exponential backoff
    return []

def fetch_historical_candles(symbol, interval="1h", limit=1000, start_time=None):
    """
    Fetches historical kline/candle data for a symbol.
    start_time: timestamp in milliseconds
    """
    params = {
        "symbol": symbol,
        "interval": interval,
        "limit": limit
    }
    if start_time is not None:
        params['startTime'] = start_time

    retries = 3
    for i in range(retries):
        try:
            response = requests.get(BINANCE_KLINES_URL, params=params, timeout=10)
            response.raise_for_status()
            return response.json()
        except requests.RequestException as e:
            print(f"Error fetching historical data for {symbol} (Attempt {i+1}/{retries}): {e}")
            time.sleep(2 * (i + 1))
    return []

def update_search_index(cursor, symbol, price, price_change):
    # Optional: Helper to update a search or cache table if exists
    pass

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

def update_historical_data(symbol, interval="1h", limit=None):
    """
    Smart sync:
    1. Check last candle time in DB.
    2. If exists, fetch from there.
    3. If not, fetch full history (from 0 if requested, or lookback). 
       Binance allows fetching from 0, but that might be heavy. 
       Let's assume user wants full history.
    """
    conn = get_db_connection()
    if not conn:
        return

    cursor = conn.cursor()
    
    # 1. Get last close time
    query = "SELECT MAX(close_time) FROM historical_candles WHERE symbol = %s AND `interval` = %s"
    cursor.execute(query, (symbol, interval))
    result = cursor.fetchone()
    last_close_time_db = result[0] if result else None
    
    start_ts = 0
    if last_close_time_db:
        # DB returns datetime. Convert to ms timestamp.
        # Add 1ms or 1 second to avoid duplicate of the very last candle, 
        # basically we want the next candle.
        start_ts = int(last_close_time_db.timestamp() * 1000) + 1
        print(f"[{symbol} {interval}] Found existing data. Creating bridge from {last_close_time_db}...")
    else:
        print(f"[{symbol} {interval}] No existing data. Fetching full history...")
        # Start from a reasonable time for crypto (e.g., 2017) or 0 to let Binance decide.
        # Binance handles 0 by returning the first available candle.
        start_ts = 0

    batch_size = 1000
    total_candles = 0
    
    while True:
        print(f"DEBUG: Fetching from start_ts={start_ts} limit={batch_size}")
        candles = fetch_historical_candles(symbol, interval, limit=batch_size, start_time=start_ts)
        
        if not candles:
            print("DEBUG: No candles returned.")
            break
            
        print(f"Fetched {len(candles)} candles locally...")
        
        sql = """
        REPLACE INTO historical_candles (symbol, `interval`, open, high, low, close, volume, close_time)
        VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
        """
        
        batch_values = []
        last_candle_close_ts = 0
        
        for candle in candles:
            # Binance kline format index 6 is Close Time (ms)
            close_time_ts = candle[6]
            last_candle_close_ts = close_time_ts
            
            close_time = datetime.fromtimestamp(close_time_ts / 1000)
            
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
            batch_values.append(val)
        
        print(f"DEBUG: Last close TS: {last_candle_close_ts}, New Start TS: {last_candle_close_ts + 1}")
        
        if batch_values:
            try:
                cursor.executemany(sql, batch_values)
                conn.commit()
                total_candles += len(batch_values)
            except Exception as e:
                print(f"Error inserting batch: {e}")
        
        # Prepare for next iteration
        # If we received fewer candles than batch_limit, we are likely at the head
        if len(candles) < batch_size:
            break
            
        # Update start_ts to the close_time of the last candle + 1ms to get the next one
        start_ts = last_candle_close_ts + 1
        
        # Rate limit safety
        time.sleep(0.1)

    print(f"[{symbol} {interval}] Sync complete. Total candles updated: {total_candles}")

# --- Kraken Integration ---

KRAKEN_OHLC_URL = "https://api.kraken.com/0/public/OHLC"

def fetch_kraken_ohlc(pair, interval):
    """
    Fetches OHLC data from Kraken.
    Pair: e.g., XMRUSD
    Interval: e.g., '1h' (mapped to Kraken minutes)
    """
    # Map Binance-style intervals to Kraken minutes
    interval_map = {
        "15m": 15,
        "1h": 60,
        "4h": 240,
        "1d": 1440,
        "1w": 10080,
        "1M": 21600 # 15 days is closest max, but Kraken doesn't do 1M perfectly. 
                    # 21600 = 15 days.
    }
    
    kraken_interval = interval_map.get(interval, 60)
    
    params = {
        "pair": pair,
        "interval": kraken_interval
    }
    
    try:
        response = requests.get(KRAKEN_OHLC_URL, params=params, timeout=10)
        response.raise_for_status()
        return response.json()
    except Exception as e:
        print(f"Error fetching Kraken data for {pair}: {e}")
        return None

def update_historical_data_from_kraken(symbol, interval="1h"):
    """
    Fetches data from Kraken and upserts into DB.
    Requires symbol mapping (e.g. XMRUSDT -> XMRUSD).
    """
    # Simple mapping strategy for MVP
    # If ends with USDT, try replacing with USD.
    kraken_pair = symbol
    if symbol.endswith('USDT'):
        kraken_pair = symbol.replace('USDT', 'USD')
    
    print(f"[{symbol} ({kraken_pair})] fetching from Kraken...")
    
    data = fetch_kraken_ohlc(kraken_pair, interval)
    
    if not data or 'result' not in data:
        print(f"Invalid response from Kraken for {kraken_pair}")
        return

    # Kraken returns 'result': { 'XXMRZUSD': [[...]], 'last': ... }
    # Since the key name is dynamic (e.g. XXMRZUSD), we just take the first list value found.
    result_data = data['result']
    candles = []
    
    for key, val in result_data.items():
        if key == 'last': continue
        if isinstance(val, list):
            candles = val
            break
            
    if not candles:
        print("No candles found in Kraken response.")
        return

    conn = get_db_connection()
    if not conn: return
    cursor = conn.cursor()

    sql = """
    REPLACE INTO historical_candles (symbol, `interval`, open, high, low, close, volume, close_time)
    VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
    """

    count = 0
    batch_values = []
    
    for candle in candles:
        # Kraken Format: [int <time>, string <open>, string <high>, string <low>, string <close>, string <vwap>, string <volume>, int <count>]
        # Time is unix timestamp (seconds)
        
        ts = int(candle[0])
        close_time = datetime.fromtimestamp(ts)
        
        # Open, High, Low, Close are strings
        op = float(candle[1])
        hi = float(candle[2])
        lo = float(candle[3])
        cl = float(candle[4])
        vol = float(candle[6])
        
        val = (
            symbol, # Store as original symbol (XMRUSDT) to match app schema
            interval, 
            op, hi, lo, cl, vol, 
            close_time
        )
        batch_values.append(val)
        count += 1
        
    if batch_values:
        try:
            cursor.executemany(sql, batch_values)
            conn.commit()
            print(f"[{symbol}] Kraken Sync: Imported {len(batch_values)} candles for {interval}.")
        except Exception as e:
            print(f"Error inserting Kraken batch: {e}")

    cursor.close()
    conn.close()

if __name__ == "__main__":
    # Define intervals to track
    intervals = ["15m", "1h", "4h", "1d", "1w", "1M"]
    
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
                print(f"Processing {item['symbol']}...")
                for interval in intervals:
                    update_historical_data(item['symbol'], interval)
                    time.sleep(0.5) # Avoid rate limits
                
        print("Sleeping for 60s...")
        time.sleep(60)
