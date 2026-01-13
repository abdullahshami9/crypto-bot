import argparse
import sys
import json
from data_ingest import update_historical_data, fetch_market_data, update_market_data, update_historical_data_from_kraken

def sync_coin(symbol, source='binance'):
    intervals = ["15m", "1h", "4h", "1d", "1w", "1M"]
    try:
        print(f"Syncing {symbol} from {source}...")

        if source == 'kraken':
            # Kraken typically uses XMRUSD or XMRUSDT (if available). 
            # We need to ensure the symbol is formatted correctly for Kraken.
            # For simplicity, pass the symbol as is, handle mapping in ingest.
            for interval in intervals:
                update_historical_data_from_kraken(symbol, interval)
        else:
            # Default Binance
            for interval in intervals:
                update_historical_data(symbol, interval, limit=1000)

        return True, "Sync complete"
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
