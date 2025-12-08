import time
import threading
import warnings
import json
import os
import warnings
import data_ingest
import analysis_engine
import trading_engine
import learning_engine
import prediction_engine
from db_utils import get_db_connection

# Global flag for shutdown
running = True

def market_monitor():
    """
    Thread 1: Real-time Market Data, Trading Signals, and Risk Management
    """
    print(" [Thread-1] Market Monitor Started")
    global running
    
    while running:
        try:
            # 1. Data Ingestion
            # print("\n[Market] Fetching Market Data...")
            market_data = data_ingest.fetch_market_data()
            if market_data:
                data_ingest.update_market_data(market_data)
                
                # Filter for top volume USDT pairs to analyze
                stablecoins = ['USDCUSDT', 'FDUSDUSDT', 'TUSDUSDT', 'DAIUSDT', 'USDPUSDT', 'BUSDUSDT']
                usdt_pairs = [
                    item for item in market_data 
                    if item['symbol'].endswith('USDT') and item['symbol'] not in stablecoins
                ]
                top_pairs = sorted(usdt_pairs, key=lambda x: float(x['quoteVolume']), reverse=True)[:20]
                
                # 2. Analysis & Trading
                
                # Get current prices for SL/TP check
                current_prices = {item['symbol']: float(item['lastPrice']) for item in usdt_pairs}
                trading_engine.check_risk_management(current_prices)
                
                for item in top_pairs:
                    symbol = item['symbol']
                    
                    # Ensure we have history
                    data_ingest.update_historical_data(symbol)
                    
                    # Analyze for Trading Signals
                    current_price = current_prices.get(symbol)
                    signal = analysis_engine.analyze_market(symbol, current_price)
                    if signal:
                        print(f"[Signal] {symbol}: {signal['signal']} (Score: {signal['score']})")
                        analysis_engine.save_signal(signal)
                        
                        # Trade
                        trading_engine.execute_trade(signal)
                        
            # 3. Learning (Periodically)
            # We can run this less frequently, e.g., every loop or every N loops
            learning_engine.analyze_performance()
            
            # Sleep logic for main loop
            time.sleep(60)
            
        except Exception as e:
            print(f"[Critical Error] Market Monitor: {e}")
            import traceback
            traceback.print_exc()
            time.sleep(60)

def prediction_service():
    """
    Thread 2: Periodic AI Predictions for all monitored symbols
    """
    print(" [Thread-2] Prediction Service Started")
    global running
    
    FOCUS_FILE = 'focus.json'
    
    while running:
        try:
            # Check for focused symbol
            active_symbol = None
            if os.path.exists(FOCUS_FILE):
                try:
                    with open(FOCUS_FILE, 'r') as f:
                        focus_data = json.load(f)
                        if time.time() - focus_data.get('timestamp', 0) < 120: # Focus valid for 2 mins
                            active_symbol = focus_data.get('symbol')
                except:
                    pass

            conn = get_db_connection()
            if not conn:
                time.sleep(60)
                continue
                
            cursor = conn.cursor(dictionary=True)
            
            # If we have an active symbol, predict for it IMMEDIATELY
            if active_symbol:
                # print(f"[Prediction] Priority Analysis: {active_symbol}")
                try:
                    # Update History First
                    data_ingest.update_historical_data(active_symbol)
                    
                    # Generate Prediction
                    intervals = ['15m', '1h'] # Fast intervals for active focus
                    for interval in intervals:
                        pred = prediction_engine.generate_prediction(active_symbol, interval)
                        if pred:
                            prediction_engine.save_prediction(pred)
                except Exception as e:
                    print(f"[Focus Error] {active_symbol}: {e}")

            # Normal Batch (Top 20)
            # Run this less frequently if focus is active, or run in background
            # To utilize "Single Server" efficiently, we will run batch every few loops
            # or interleaved. For now, let's run batch every loop but sleep longer.
            
            cursor.execute("SELECT symbol FROM coins WHERE symbol LIKE '%USDT' ORDER BY volume DESC LIMIT 20")
            symbols = [row['symbol'] for row in cursor.fetchall()]
            conn.close()
            
            # print(f"\n[Prediction] Generating forecasts for {len(symbols)} symbols...")
            
            for symbol in symbols:
                if not running: break
                
                # specific check for active symbol if we just did it
                if symbol == active_symbol: continue
                
                intervals = ['1h', '4h']
                for interval in intervals:
                    try:
                        # data_ingest.update_historical_data(symbol) -- relied on market_monitor for updates
                        pred = prediction_engine.generate_prediction(symbol, interval)
                        if pred:
                            prediction_engine.save_prediction(pred)
                    except Exception as p_err:
                        # print(f"  ! Error optimizing {symbol} {interval}: {p_err}")
                        pass
                        
            # Sleep logic
            # If active symbol exists, loop faster (e.g., 10s) to keep it fresh
            # If not, sleep longer (5m)
            sleep_time = 10 if active_symbol else 300
            
            # print(f"[Prediction] Sleeping {sleep_time}s...")
            for _ in range(sleep_time):
                if not running: break
                time.sleep(1)
                
        except Exception as e:
            print(f"[Critical Error] Prediction Service: {e}")
            time.sleep(60)

def main():
    print("==========================================")
    print("   CRYPTO INTELLIGENCE ENGINE v2.0")
    print("   Integrated Trading & Prediction System")
    print("==========================================")
    print("Press Ctrl+C to stop.\n")
    
    t1 = threading.Thread(target=market_monitor)
    t2 = threading.Thread(target=prediction_service)
    
    t1.daemon = True
    t2.daemon = True
    
    t1.start()
    t2.start()
    
    try:
        while True:
            time.sleep(1)
    except KeyboardInterrupt:
        print("\nStopping Engine...")
        global running
        running = False
        print("Waiting for threads to finish...")
        # t1.join() # Daemon threads will be killed when main exits, which is fine for now
        print("Goodbye.")

if __name__ == "__main__":
    main()
