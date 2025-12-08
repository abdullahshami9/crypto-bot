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

def check_predictions():
    """
    Verifies past predictions to see if they were correct.
    Updates the 'outcome' column in the predictions table.
    """
    conn = get_db_connection()
    if not conn: return

    cursor = conn.cursor(dictionary=True)
    
    # 1. Ensure 'outcome' column exists
    try:
        cursor.execute("SHOW COLUMNS FROM predictions LIKE 'outcome'")
        result = cursor.fetchone()
        if not result:
            print("Adding 'outcome' column to predictions table...")
            cursor.execute("ALTER TABLE predictions ADD COLUMN outcome VARCHAR(20) DEFAULT 'PENDING'")
            conn.commit()
    except Exception as e:
        print(f"Error checking schema: {e}")

    # 2. Fetch Pending Predictions that are due
    # predicted_time is the END of the interval. So if now > prediction_time, we can verify.
    # Limit to 50 to avoid hogging
    cursor.execute("""
        SELECT * FROM predictions 
        WHERE outcome = 'PENDING' AND prediction_time < NOW() 
        ORDER BY prediction_time ASC 
        LIMIT 50
    """)
    pending = cursor.fetchall()
    
    updates = []
    
    for p in pending:
        symbol = p['symbol']
        pred_close = float(p['predicted_close'])
        pred_open = float(p['predicted_open']) # This was the price at valid_at (start)
        
        # Determine direction
        is_long = pred_close > pred_open
        
        # We need to find highest/lowest point between creation and prediction_time
        # For simplicity, we just check the candle that covers this prediction window.
        # Since we don't store "created_at" in the table explicitly (except maybe default timestamp?), 
        # we can assume prediction covers the candles having close_time <= prediction_time
        # and close_time > prediction_time - interval.
        
        # Let's simple check the price history
        
        start_time_guess = p['prediction_time'] - timedelta(hours=1) # rough guess for 1h
        if p['interval'] == '15m': start_time_guess = p['prediction_time'] - timedelta(minutes=15)
        elif p['interval'] == '4h': start_time_guess = p['prediction_time'] - timedelta(hours=4)
        
        # Query candles
        sql = f"""
            SELECT high, low, close FROM historical_candles 
            WHERE symbol = '{symbol}' 
            AND close_time <= '{p['prediction_time']}' 
            AND close_time > '{start_time_guess}'
        """
        cursor.execute(sql)
        candles = cursor.fetchall()
        
        outcome = 'FAILURE'
        
        if candles:
            # Check if target was hit in any candle
            max_h = max([float(c['high']) for c in candles])
            min_l = min([float(c['low']) for c in candles])
            final_c = float(candles[-1]['close'])
            
            if is_long:
                # Success if we hit the target close (or higher)
                if max_h >= pred_close:
                    outcome = 'SUCCESS'
                # Also partial success if final close is significantly higher than open (even if target missed)
                elif final_c > pred_open + (pred_close - pred_open) * 0.5:
                     outcome = 'SUCCESS' # Good enough
            else:
                # Success if we hit target (or lower)
                if min_l <= pred_close:
                    outcome = 'SUCCESS'
                elif final_c < pred_open - (pred_open - pred_close) * 0.5:
                     outcome = 'SUCCESS'

        # Update
        updates.append((outcome, p['id']))
        
    # Bulk update
    if updates:
        print(f"[Prediction] Verifying {len(updates)} past outcomes...")
        upd_sql = "UPDATE predictions SET outcome = %s WHERE id = %s"
        cursor.executemany(upd_sql, updates)
        conn.commit()
        
    conn.close()

def get_success_ratio():
    conn = get_db_connection()
    if not conn: return 0
    
    cursor = conn.cursor(dictionary=True)
    
    try:
        # Check if outcome column exists first to avoid error on fresh run
        cursor.execute("SHOW COLUMNS FROM predictions LIKE 'outcome'")
        if not cursor.fetchone():
            conn.close()
            return 0

        cursor.execute("""
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN outcome = 'SUCCESS' THEN 1 ELSE 0 END) as wins
            FROM predictions 
            WHERE outcome IN ('SUCCESS', 'FAILURE')
            ORDER BY prediction_time DESC 
            LIMIT 100
        """)
        res = cursor.fetchone()
        conn.close()
        
        if res and res['total'] > 0:
            return float((res['wins'] / res['total']) * 100)
    except:
        if conn: conn.close()
    
    return 0.0
