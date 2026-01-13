import argparse
import sys
import json
from data_ingest import (
    update_historical_data,
    fetch_market_data,
    update_market_data,
    update_historical_data_from_kraken
)

def sync_coin(symbol, source='binance'):
    intervals = ["15m", "1h", "4h", "1d", "1w", "1M"]
    messages = []

    try:
        print(f"Syncing {symbol} (initial source: {source})...")

        # If source is explicit 'kraken', just use Kraken
        if source == 'kraken':
            for interval in intervals:
                count = update_historical_data_from_kraken(symbol, interval)
                messages.append(f"{interval}: {count} candles (Kraken)")
            return True, "Sync complete (Kraken). Details: " + ", ".join(messages)

        # Default or 'binance' -> Try Binance first, with fallback
        for interval in intervals:
            # Try Binance
            count = update_historical_data(symbol, interval, limit=1000)

            # If Binance failed (0 candles), try Kraken as fallback
            if count == 0:
                 print(f"Binance returned 0 candles for {interval}. Attempting Kraken fallback...")
                 k_count = update_historical_data_from_kraken(symbol, interval)
                 if k_count > 0:
                     messages.append(f"{interval}: {k_count} (Kraken)")
                 else:
                     messages.append(f"{interval}: 0 (Both failed)")
            else:
                 messages.append(f"{interval}: {count} (Binance)")

        return True, "Sync complete. " + ", ".join(messages)
    except Exception as e:
        return False, str(e)

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description='Sync coin data')
    parser.add_argument('--symbol', type=str, required=True, help='Coin symbol (e.g., BTCUSDT)')
    parser.add_argument('--source', type=str, default='binance', help='Data source (binance or kraken)')
    args = parser.parse_args()

    success, message = sync_coin(args.symbol, args.source)

    result = {
        "success": success,
        "message": message,
        "symbol": args.symbol
    }

    print(json.dumps(result))
