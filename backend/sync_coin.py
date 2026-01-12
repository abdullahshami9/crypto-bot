import argparse
import sys
import json
from data_ingest import update_historical_data, fetch_market_data, update_market_data

def sync_coin(symbol):
    intervals = ["15m", "1h", "4h", "1d", "1w", "1M"]
    try:
        # First, update the ticker data for this coin to ensure price/volume is fresh
        # fetch_market_data returns all coins, so we can just run it and update specific or all.
        # Since update_market_data processes everything, let's just do it.
        # Or to be faster, we can skip full market update if it takes too long,
        # but the user wants "complete data".

        # Let's just update history for the specific coin for all intervals.
        print(f"Syncing {symbol}...")

        for interval in intervals:
            # increasing limit to ensure we cover gaps.
            # Binance default limit in update_historical_data is 100.
            # User complained about "very previous data" (old data), so maybe we need more than 100?
            # If the gap is huge, 100 might not be enough.
            # But let's stick to existing function signature or pass a higher limit.
            # update_historical_data accepts limit.
            update_historical_data(symbol, interval, limit=500)

        return True, "Sync complete"
    except Exception as e:
        return False, str(e)

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description='Sync coin data')
    parser.add_argument('--symbol', type=str, required=True, help='Coin symbol (e.g., BTCUSDT)')
    args = parser.parse_args()

    success, message = sync_coin(args.symbol)

    result = {
        "success": success,
        "message": message,
        "symbol": args.symbol
    }

    print(json.dumps(result))
