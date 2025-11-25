import pandas as pd
import warnings
warnings.filterwarnings('ignore')
from db_utils import get_db_connection
from datetime import datetime, timedelta

def generate_prediction(symbol, interval="1h"):
    conn = get_db_connection()
    if not conn:
        return None

    # Helper to get dataframe for an interval
    def get_data(tf, limit=50):
        query = f"""
            SELECT close_time, open, high, low, close, volume 
            FROM historical_candles 
            WHERE symbol = '{symbol}' AND `interval` = '{tf}'
            ORDER BY close_time DESC 
            LIMIT {limit}
        """
        try:
            df = pd.read_sql(query, conn)
            if not df.empty:
                return df.sort_values('close_time')
        except Exception as e:
            print(f"Error reading {tf} data: {e}")
        return pd.DataFrame()

    # Fetch data for the requested interval
    df = get_data(interval)
    
    if df.empty:
        conn.close()
        return None

    # --- Analysis Logic ---
    # Simple logic for now, can be enhanced
    
    last_close = df.iloc[-1]['close']
    
    # Calculate simple moving averages
    sma_short = df['close'].rolling(window=5).mean().iloc[-1]
    sma_long = df['close'].rolling(window=20).mean().iloc[-1]
    
    # Determine bias
    bias_score = 0
    if sma_short > sma_long:
        bias_score += 30 # Increased weight
    else:
        bias_score -= 30
        
    # RSI-like logic
    delta = df['close'].diff()
    gain = (delta.where(delta > 0, 0)).rolling(window=14).mean().iloc[-1]
    loss = (-delta.where(delta < 0, 0)).rolling(window=14).mean().iloc[-1]
    rs = gain / loss if loss != 0 else 0
    rsi = 100 - (100 / (1 + rs))
    
    if rsi < 30:
        bias_score += 20 # Increased weight
    elif rsi > 70:
        bias_score -= 20
        
    # Volume Trend
    vol_sma = df['volume'].rolling(window=20).mean().iloc[-1]
    current_vol = df['volume'].iloc[-1]
    if current_vol > (vol_sma * 1.2):
        # High volume confirms the trend
        if df['close'].iloc[-1] > df['open'].iloc[-1]:
             bias_score += 10
        else:
             bias_score -= 10

    # Total Score
    total_score = bias_score
    
    # Confidence
    # Base 50. Max score is 60 (30+20+10). 50 + 60 = 110.
    confidence = 50 + abs(total_score)
    confidence = min(max(confidence, 10), 100)
    
    # Direction
    direction = 1 if total_score > 0 else -1
    
    # Volatility for target
    volatility = (df['high'] - df['low']).mean()
    target_move = volatility * (confidence / 50) * direction
    
    pred_open = last_close
    pred_close = last_close + target_move
    pred_high = max(pred_open, pred_close) + (volatility * 0.3)
    pred_low = min(pred_open, pred_close) - (volatility * 0.3)
    
    # Prediction Time
    # Calculate based on interval
    interval_minutes = 60
    if interval == '15m': interval_minutes = 15
    elif interval == '4h': interval_minutes = 240
    elif interval == '1d': interval_minutes = 1440
    elif interval == '1w': interval_minutes = 10080
    elif interval == '1M': interval_minutes = 43200
    
    prediction_time = datetime.now() + timedelta(minutes=interval_minutes)
    
    conn.close()
    
    return {
        "symbol": symbol,
        "interval": interval,
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
    INSERT INTO predictions (symbol, `interval`, prediction_time, predicted_open, predicted_high, predicted_low, predicted_close, confidence_score)
    VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
    """
    val = (
        pred_data['symbol'], 
        pred_data['interval'],
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
        print(f"Prediction saved for {pred_data['symbol']} ({pred_data['interval']})")
    except Exception as e:
        print(f"Error saving prediction: {e}")
        
    conn.close()

if __name__ == "__main__":
    intervals = ["15m", "1h", "4h", "1d", "1M"]
    symbol = "BTCUSDT"
    
    for interval in intervals:
        print(f"Generating prediction for {symbol} {interval}...")
        prediction = generate_prediction(symbol, interval)
        if prediction:
            save_prediction(prediction)
        else:
            print(f"Failed to generate prediction for {interval}.")

