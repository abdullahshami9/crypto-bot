import mysql.connector
from db_utils import get_db_connection
from sync_coin import sync_coin
from datetime import datetime, timedelta

def check_and_fix_sync():
    print("Starting Global Sync Check...")
    conn = get_db_connection()
    if not conn:
        print("Database connection failed.")
        return

    cursor = conn.cursor()

    # Get all distinct symbols from historical_candles
    cursor.execute("SELECT DISTINCT symbol FROM historical_candles")
    symbols = [row[0] for row in cursor.fetchall()]

    print(f"Found {len(symbols)} coins to check.")

    for symbol in symbols:
        # Check max date for 1d and 1h
        cursor.execute("SELECT MAX(close_time) FROM historical_candles WHERE symbol = %s AND `interval` = '1d'", (symbol,))
        res_1d = cursor.fetchone()
        max_1d = res_1d[0] if res_1d else None

        cursor.execute("SELECT MAX(close_time) FROM historical_candles WHERE symbol = %s AND `interval` = '1h'", (symbol,))
        res_1h = cursor.fetchone()
        max_1h = res_1h[0] if res_1h else None

        needs_sync = False
        reason = ""

        if max_1d and max_1h:
            diff = max_1d - max_1h
            if abs(diff.days) > 2:
                needs_sync = True
                reason = f"Gap > 2 days (1d: {max_1d}, 1h: {max_1h})"
        elif max_1d and not max_1h:
             needs_sync = True
             reason = "Missing 1h data"
        elif not max_1d and max_1h:
             needs_sync = True
             reason = "Missing 1d data"

        # Specific check for XMRUSDT as requested by user
        if symbol == 'XMRUSDT':
            # Force check or just relying on above logic?
            # User said "run a global sync if find any difference".
            # The above logic covers the difference.
            pass

        if needs_sync:
            print(f"DISCREPANCY FOUND for {symbol}: {reason}. Triggering sync...")
            success, msg = sync_coin(symbol, source='binance') # Will fallback to Kraken if needed
            print(f"Sync Result: {success} - {msg}")
        else:
            print(f"OK: {symbol} (1d: {max_1d}, 1h: {max_1h})")

    cursor.close()
    conn.close()
    print("Global Sync Check Complete.")

if __name__ == "__main__":
    check_and_fix_sync()
