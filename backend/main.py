import time
import data_ingest
import analysis_engine
import trading_engine
import learning_engine
from db_utils import get_db_connection

def main():
    print("Starting Crypto Intelligence Engine...")
    
    while True:
        try:
            # 1. Data Ingestion
            print("\n[1] Fetching Market Data...")
            market_data = data_ingest.fetch_market_data()
            if market_data:
                data_ingest.update_market_data(market_data)
                
                # Filter for top volume USDT pairs to analyze
                usdt_pairs = [item for item in market_data if item['symbol'].endswith('USDT')]
                top_pairs = sorted(usdt_pairs, key=lambda x: float(x['quoteVolume']), reverse=True)[:20]
                
                # 2. Analysis & Trading
                print("[2] Analyzing & Trading...")
                
                # Get current prices for SL/TP check
                current_prices = {item['symbol']: float(item['lastPrice']) for item in usdt_pairs}
                trading_engine.check_stop_loss_take_profit(current_prices)
                
                for item in top_pairs:
                    symbol = item['symbol']
                    
                    # Ensure we have history
                    data_ingest.update_historical_data(symbol)
                    
                    # Analyze
                    signal = analysis_engine.analyze_market(symbol)
                    if signal:
                        print(f"Signal for {symbol}: {signal['signal']} (Score: {signal['score']})")
                        analysis_engine.save_signal(signal)
                        
                        # Trade
                        trading_engine.execute_trade(signal)
                        
            # 3. Learning
            print("[3] Learning...")
            learning_engine.review_trades()
            
            print("Cycle complete. Sleeping for 60s...")
            time.sleep(60)
            
        except Exception as e:
            print(f"Critical Error in Main Loop: {e}")
            time.sleep(60)

if __name__ == "__main__":
    main()
