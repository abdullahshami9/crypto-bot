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

    # Helper to get dataframe for an interval
    def get_data(interval, limit=50):
        query = f"""
            SELECT close_time, open, high, low, close, volume 
            FROM historical_candles 
            WHERE symbol = '{symbol}' AND `interval` = '{interval}'
            ORDER BY close_time DESC 
            LIMIT {limit}
        """
        try:
            df = pd.read_sql(query, conn)
            if not df.empty:
                return df.sort_values('close_time')
        except Exception as e:
            print(f"Error reading {interval} data: {e}")
        return pd.DataFrame()

    # Fetch data for multiple timeframes
    df_15m = get_data('15m')
    df_1h = get_data('1h')
    df_4h = get_data('4h')
    df_1d = get_data('1d')
    
    if df_15m.empty:
        conn.close()
        return None

    # --- Analysis Logic ---
    
    # 1. Long-Term Trend (Daily/4H) - Sets the Bias
    bias_score = 0
    if not df_1d.empty:
        sma_20_d = df_1d['close'].rolling(window=20).mean().iloc[-1]
        last_close_d = df_1d.iloc[-1]['close']
        if last_close_d > sma_20_d:
            bias_score += 20 # Bullish Bias
        else:
            bias_score -= 20 # Bearish Bias
            
    if not df_4h.empty:
        sma_20_4h = df_4h['close'].rolling(window=20).mean().iloc[-1]
        last_close_4h = df_4h.iloc[-1]['close']
        if last_close_4h > sma_20_4h:
            bias_score += 10
        else:
            bias_score -= 10

    # 2. Medium-Term Momentum (1H)
    momentum_score = 0
    if not df_1h.empty:
        # RSI-like logic (simplified)
        delta = df_1h['close'].diff()
        gain = (delta.where(delta > 0, 0)).rolling(window=14).mean().iloc[-1]
        loss = (-delta.where(delta < 0, 0)).rolling(window=14).mean().iloc[-1]
        rs = gain / loss if loss != 0 else 0
        rsi = 100 - (100 / (1 + rs))
        
        if rsi < 30:
            momentum_score += 15 # Oversold -> Buy
        elif rsi > 70:
            momentum_score -= 15 # Overbought -> Sell
        
        # Trend Confirmation
        sma_short_1h = df_1h['close'].rolling(window=5).mean().iloc[-1]
        sma_long_1h = df_1h['close'].rolling(window=20).mean().iloc[-1]
        if sma_short_1h > sma_long_1h:
            momentum_score += 10
        else:
            momentum_score -= 10

    # 3. Short-Term Entry (15m)
    entry_score = 0
    last_close = df_15m.iloc[-1]['close']
    volatility = (df_15m['high'] - df_15m['low']).mean()
    
    sma_short_15m = df_15m['close'].rolling(window=5).mean().iloc[-1]
    sma_long_15m = df_15m['close'].rolling(window=20).mean().iloc[-1]
    
    if sma_short_15m > sma_long_15m:
        entry_score += 15
    else:
        entry_score -= 15

    # Total Score Calculation
    total_score = bias_score + momentum_score + entry_score
    
    # Normalize to Confidence (0-100)
    # Score range approx -70 to +70
    confidence = 50 + (total_score / 1.4) 
    confidence = min(max(confidence, 10), 95)
    
    # Determine Direction
    direction = 1 if total_score > 0 else -1
    
    # Predict Target
    # Volatility-based target
    target_move = volatility * (confidence / 50) * direction
    
    pred_open = last_close
    pred_close = last_close + target_move
    pred_high = max(pred_open, pred_close) + (volatility * 0.3)
    pred_low = min(pred_open, pred_close) - (volatility * 0.3)
    
    # Win Rate Bonus (from learning)
    cursor = conn.cursor()
    cursor.execute("SELECT COUNT(*) as total, SUM(CASE WHEN reward_score > 0 THEN 1 ELSE 0 END) as wins FROM trade_learning")
    stats = cursor.fetchone()
    if stats and stats[0] > 0:
        win_rate = stats[1] / stats[0]
        # Adjust confidence slightly based on bot's past performance
        confidence += (win_rate - 0.5) * 10 
        confidence = min(max(confidence, 10), 95)

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
