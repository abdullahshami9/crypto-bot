import time
import warnings
warnings.filterwarnings('ignore')
import data_ingest
import analysis_engine
import trading_engine
import learning_engine
import prediction_engine
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
                # Exclude Stablecoins
                stablecoins = ['USDCUSDT', 'FDUSDUSDT', 'TUSDUSDT', 'DAIUSDT', 'USDPUSDT', 'BUSDUSDT']
                usdt_pairs = [
                    item for item in market_data 
                    if item['symbol'].endswith('USDT') and item['symbol'] not in stablecoins
                ]
                top_pairs = sorted(usdt_pairs, key=lambda x: float(x['quoteVolume']), reverse=True)[:20]
                
                # 2. Analysis & Trading
                print("[2] Analyzing & Trading...")
                
                # Get current prices for SL/TP check
                current_prices = {item['symbol']: float(item['lastPrice']) for item in usdt_pairs}
                trading_engine.check_risk_management(current_prices)
                
                for item in top_pairs:
                    symbol = item['symbol']
                    
                    # Ensure we have history
                    data_ingest.update_historical_data(symbol)
                    
                    # Analyze
                    current_price = current_prices.get(symbol)
                    signal = analysis_engine.analyze_market(symbol, current_price)
                    if signal:
                        print(f"Signal for {symbol}: {signal['signal']} (Score: {signal['score']})")
                        analysis_engine.save_signal(signal)
                        
                        # Trade
                        trading_engine.execute_trade(signal)
                        
                        # Generate Prediction
                        prediction = prediction_engine.generate_prediction(symbol)
                        if prediction:
                            prediction_engine.save_prediction(prediction)
                        
            # 3. Learning
            print("[3] Learning...")
            learning_engine.analyze_performance()
            
            print("Cycle complete. Sleeping for 60s...")
            time.sleep(60)
            
        except Exception as e:
            print(f"Critical Error in Main Loop: {e}")
            time.sleep(60)

if __name__ == "__main__":
    main()
