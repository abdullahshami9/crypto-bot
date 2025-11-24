import pandas as pd
import warnings
warnings.filterwarnings('ignore')
# import pandas_ta as ta
from db_utils import get_db_connection
from datetime import datetime, timedelta
import random

def generate_prediction(symbol):
    conn = get_db_connection()
    if not conn:
        return None

    # Get recent history to base prediction on
    query = f"""
        SELECT close_time, open, high, low, close, volume 
        FROM historical_candles 
        WHERE symbol = '{symbol}' 
        ORDER BY close_time DESC 
        LIMIT 100
    """
    
    import warnings
    with warnings.catch_warnings():
        warnings.filterwarnings("ignore", category=UserWarning, module="pandas")
        try:
            df = pd.read_sql(query, conn)
        except Exception as e:
            print(f"Error reading data: {e}")
            conn.close()
            return None
            
            
    # conn.close() - Removed premature close
        
    if df.empty:
        return None

    # Sort by time ascending for calculation
    df = df.sort_values('close_time')
    
    # Calculate simple indicators for "prediction"
    # In a real scenario, this would be a loaded ML model
    last_close = df.iloc[-1]['close']
    last_high = df.iloc[-1]['high']
    last_low = df.iloc[-1]['low']
    
    # Simple Volatility based on ATR-like logic (High - Low)
    volatility = (df['high'] - df['low']).mean()
    
    # Simple Trend (SMA)
    sma_short = df['close'].rolling(window=5).mean().iloc[-1]
    sma_long = df['close'].rolling(window=20).mean().iloc[-1]
    
    trend_direction = 1 if sma_short > sma_long else -1
    
    # Predict next 15m candle
    # Random factor to simulate market noise
    noise = random.uniform(-0.5, 0.5) * volatility
    
    predicted_change = (volatility * 0.5 * trend_direction) + noise
    
    pred_open = last_close
    pred_close = last_close + predicted_change
    pred_high = max(pred_open, pred_close) + (volatility * 0.2)
    pred_low = min(pred_open, pred_close) - (volatility * 0.2)
    
    # Calculate Win Rate from Learning History
    cursor = conn.cursor()
    cursor.execute("SELECT COUNT(*) as total, SUM(CASE WHEN reward_score > 0 THEN 1 ELSE 0 END) as wins FROM trade_learning")
    stats = cursor.fetchone()
    
    win_rate_bonus = 0
    if stats and stats[0] > 0:
        win_rate = stats[1] / stats[0]
        win_rate_bonus = (win_rate - 0.5) * 20 # +/- bonus based on win rate vs 50%
    
    # Confidence score based on trend strength + Bot Win Rate
    confidence = 50 + (abs(sma_short - sma_long) / last_close * 1000) + win_rate_bonus
    confidence = min(max(confidence, 10), 95) # Cap between 10 and 95
    
    # Prediction time is 15 mins from now (approx, just for display)
    # In reality, we'd align this to the next candle close time
    prediction_time = datetime.now() + timedelta(minutes=15)
    
    conn.close()
    
    return {
        "symbol": symbol,
        "prediction_time": prediction_time,
        "open": pred_open,
        "high": pred_high,
        "low": pred_low,
        "close": pred_close,
        "confidence": confidence
    }

def save_prediction(pred_data):
    conn = get_db_connection()
    if not conn:
        return

    cursor = conn.cursor()
    sql = """
    INSERT INTO predictions (symbol, prediction_time, predicted_open, predicted_high, predicted_low, predicted_close, confidence_score)
    VALUES (%s, %s, %s, %s, %s, %s, %s)
    """
    val = (
        pred_data['symbol'], 
        pred_data['prediction_time'], 
        pred_data['open'], 
        pred_data['high'], 
        pred_data['low'], 
        pred_data['close'], 
        pred_data['confidence']
    )
    
    try:
        cursor.execute(sql, val)
        conn.commit()
        print(f"Prediction saved for {pred_data['symbol']}")
    except Exception as e:
        print(f"Error saving prediction: {e}")
        
    conn.close()

if __name__ == "__main__":
    # Test
    symbol = "BTCUSDT"
    print(f"Generating prediction for {symbol}...")
    prediction = generate_prediction(symbol)
    if prediction:
        print(f"Prediction: {prediction}")
        save_prediction(prediction)
    else:
        print("Failed to generate prediction.")
