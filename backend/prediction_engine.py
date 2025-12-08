import pandas as pd
import warnings
warnings.filterwarnings('ignore')
from db_utils import get_db_connection, log_db_error
from datetime import datetime, timedelta
import pandas_ta as ta

def generate_prediction(symbol, interval="1h"):
    conn = get_db_connection()
    if not conn:
        return None

    # Helper to get dataframe for an interval
    def get_data(tf, limit=100):
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
    
    if df.empty or len(df) < 50:
        if conn: conn.close()
        return None

    # --- Advanced Analysis Logic ---

    # Calculate Indicators
    df.ta.rsi(length=14, append=True)
    df.ta.macd(append=True)
    df.ta.bbands(length=20, std=2, append=True)
    df.ta.atr(append=True)
    df.ta.sma(length=50, append=True)
    df.ta.sma(length=200, append=True)

    last_row = df.iloc[-1]
    prev_row = df.iloc[-2]
    
    close = last_row['close']

    # Dynamic Column Lookup
    rsi_col = next((c for c in df.columns if c.startswith('RSI_')), None)
    macd_col = next((c for c in df.columns if c.startswith('MACD_') and not c.startswith('MACDs') and not c.startswith('MACDh')), None)
    macdsignal_col = next((c for c in df.columns if c.startswith('MACDs_')), None)
    upper_band_col = next((c for c in df.columns if c.startswith('BBU_')), None)
    lower_band_col = next((c for c in df.columns if c.startswith('BBL_')), None)
    atr_col = next((c for c in df.columns if c.startswith('ATRr_') or c.startswith('ATR_')), None)
    sma50_col = next((c for c in df.columns if c.startswith('SMA_50')), None)
    sma200_col = next((c for c in df.columns if c.startswith('SMA_200')), None)

    # Extract values safely
    rsi = last_row[rsi_col] if rsi_col else None
    macd = last_row[macd_col] if macd_col else None
    macdsignal = last_row[macdsignal_col] if macdsignal_col else None
    upper_band = last_row[upper_band_col] if upper_band_col else None
    lower_band = last_row[lower_band_col] if lower_band_col else None
    atr = last_row[atr_col] if atr_col else None
    sma50 = last_row[sma50_col] if sma50_col else None
    sma200 = last_row[sma200_col] if sma200_col else None

    score = 0
    reasons = []

    # 1. Trend Analysis (SMA)
    if sma50 is not None and sma200 is not None and pd.notna(sma50) and pd.notna(sma200):
        if sma50 > sma200:
            score += 10
            reasons.append("Golden Cross / Bullish Trend (SMA50 > SMA200)")
        elif sma50 < sma200:
            score -= 10
            reasons.append("Death Cross / Bearish Trend (SMA50 < SMA200)")

    if sma50 is not None and pd.notna(sma50):
        if close > sma50:
            score += 5
            reasons.append("Price above SMA50")
        else:
            score -= 5
            reasons.append("Price below SMA50")

    # 2. Momentum (RSI)
    if rsi is not None and pd.notna(rsi):
        if rsi < 30:
            score += 20
            reasons.append(f"Oversold RSI ({rsi:.2f}) - Potential Bounce")
        elif rsi > 70:
            score -= 20
            reasons.append(f"Overbought RSI ({rsi:.2f}) - Potential Pullback")
        else:
            # Neutral but check slope
            if rsi > prev_row[rsi_col]:
                score += 5
            else:
                score -= 5

    # 3. MACD
    if macd is not None and macdsignal is not None and pd.notna(macd) and pd.notna(macdsignal):
        if macd > macdsignal:
            score += 15
            reasons.append("MACD Bullish Crossover")
        else:
            score -= 15
            reasons.append("MACD Bearish Crossover")

    # 4. Bollinger Bands
    if lower_band is not None and upper_band is not None and pd.notna(lower_band) and pd.notna(upper_band):
        if close < lower_band:
            score += 15
            reasons.append("Price below Lower Bollinger Band - Reversion likely")
        elif close > upper_band:
            score -= 15
            reasons.append("Price above Upper Bollinger Band - Reversion likely")

    # 5. Volume Analysis
    vol_sma = df['volume'].rolling(window=20).mean().iloc[-1]
    if last_row['volume'] > vol_sma * 1.5:
        if close > last_row['open']:
            score += 10
            reasons.append("High Volume Buying")
        else:
            score -= 10
            reasons.append("High Volume Selling")

    # Final Decision
    confidence = 50 + abs(score)
    confidence = min(max(confidence, 10), 95) # Cap at 95%
    
    direction = 1 if score > 0 else -1
    
    # Dynamic Target Calculation based on ATR
    atr_val = atr if (atr is not None and pd.notna(atr)) else (close * 0.02) # Fallback
    
    # Cap ATR at 5% of price to prevent massive candles
    max_atr = close * 0.05
    if atr_val > max_atr:
        atr_val = max_atr
        
    target_move = atr_val * 2 * direction # Target is 2x ATR
    
    # Final safety clamp on target move (max 10% move total)
    max_move = close * 0.1
    if abs(target_move) > max_move:
        target_move = max_move * direction
    
    pred_open = close
    pred_close = close + target_move
    
    pred_high = max(pred_open, pred_close) + (atr_val * 0.5)
    pred_low = min(pred_open, pred_close) - (atr_val * 0.5)
    
    # Prediction Time
    interval_minutes = 60
    if interval == '15m': interval_minutes = 15
    elif interval == '4h': interval_minutes = 240
    elif interval == '1d': interval_minutes = 1440
    elif interval == '1w': interval_minutes = 10080
    elif interval == '1M': interval_minutes = 43200
    
    prediction_time = datetime.now() + timedelta(minutes=interval_minutes)
    
    reasoning_text = "; ".join(reasons)
    if not reasoning_text:
        reasoning_text = "Neutral market conditions."
    
    conn.close()
    
    return {
        "symbol": symbol,
        "interval": interval,
        "prediction_time": prediction_time,
        "open": pred_open,
        "high": pred_high,
        "low": pred_low,
        "close": pred_close,
        "confidence": confidence,
        "reasoning": reasoning_text
    }

def save_prediction(pred_data):
    conn = get_db_connection()
    if not conn:
        return

    cursor = conn.cursor()
    
    # Removed the inline schema check to prevent "Unread result found" error.
    # We handle schema issues via exception handling below.

    sql = """
    INSERT INTO predictions (symbol, `interval`, prediction_time, predicted_open, predicted_high, predicted_low, predicted_close, confidence_score, reasoning)
    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)
    """
    val = (
        pred_data['symbol'], 
        pred_data['interval'],
        pred_data['prediction_time'], 
        pred_data['open'], 
        pred_data['high'], 
        pred_data['low'], 
        pred_data['close'], 
        pred_data['confidence'],
        pred_data.get('reasoning', '')
    )
    
    try:
        cursor.execute(sql, val)
        conn.commit()
        print(f"Prediction saved for {pred_data['symbol']} ({pred_data['interval']})")
    except Exception as e:
        print(f"Error saving prediction: {e}")
        # Log to generic table
        log_db_error('prediction_engine.py', 'save_prediction', str(e), sql)
        
        # Attempt to fix schema if it's the missing column error (1054)
        if "Unknown column 'reasoning'" in str(e):
            try:
                print("Attempting to fix schema...")
                cursor.execute("ALTER TABLE predictions ADD COLUMN reasoning TEXT")
                conn.commit()
                # Retry insert
                cursor.execute(sql, val)
                conn.commit()
                print("Schema fixed and prediction saved.")
            except Exception as schema_err:
                print(f"Failed to fix schema: {schema_err}")
                log_db_error('prediction_engine.py', 'save_prediction_schema_fix', str(schema_err), "ALTER TABLE...")

    conn.close()

if __name__ == "__main__":
    intervals = ["15m", "1h", "4h", "1d", "1M"]
    symbol = "BTCUSDT"
    
    for interval in intervals:
        print(f"Generating prediction for {symbol} {interval}...")
        prediction = generate_prediction(symbol, interval)
        if prediction:
            save_prediction(prediction)
            print(f"Reasoning: {prediction['reasoning']}")
        else:
            print(f"Failed to generate prediction for {interval}.")
