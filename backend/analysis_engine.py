import pandas as pd
import warnings
warnings.filterwarnings('ignore')
from db_utils import get_db_connection
import json

def get_historical_data(symbol, limit=100):
    conn = get_db_connection()
    if not conn:
        return None
    
    query = f"""
        SELECT close_time, open, high, low, close, volume 
        FROM historical_candles 
        WHERE symbol = '{symbol}' 
        ORDER BY close_time ASC 
        LIMIT {limit}
    """
    
    import warnings
    # Suppress the specific SQLAlchemy warning
    with warnings.catch_warnings():
        warnings.filterwarnings("ignore", category=UserWarning, module="pandas")
        try:
            df = pd.read_sql(query, conn)
        except Exception as e:
            print(f"Error reading data: {e}")
            conn.close()
            return None
            
    conn.close()
    
    if df.empty:
        return None
        
    # Ensure numeric types
    cols = ['open', 'high', 'low', 'close', 'volume']
    df[cols] = df[cols].apply(pd.to_numeric)
    return df

def analyze_market(symbol, current_price=None):
    df = get_historical_data(symbol)
    if df is None or len(df) < 30:
        return None

    # Update latest candle with real-time price if provided
    if current_price is not None:
        # Assuming the last row is the "current" candle (forming) or the most recent closed one.
        # We update the last close to reflect the real-time price for accurate indicator calculation.
        df.iloc[-1, df.columns.get_loc('close')] = float(current_price)
        # Also update high/low if current price breaks them
        if float(current_price) > df.iloc[-1]['high']:
            df.iloc[-1, df.columns.get_loc('high')] = float(current_price)
        if float(current_price) < df.iloc[-1]['low']:
            df.iloc[-1, df.columns.get_loc('low')] = float(current_price)

    # Calculate Indicators
    # RSI
    delta = df['close'].diff()
    gain = (delta.where(delta > 0, 0)).rolling(window=14).mean()
    loss = (-delta.where(delta < 0, 0)).rolling(window=14).mean()
    rs = gain / loss
    df['rsi'] = 100 - (100 / (1 + rs))
    
    # MACD
    exp1 = df['close'].ewm(span=12, adjust=False).mean()
    exp2 = df['close'].ewm(span=26, adjust=False).mean()
    macd_line = exp1 - exp2
    signal_line = macd_line.ewm(span=9, adjust=False).mean()
    
    # Add to dataframe
    df['MACDh_12_26_9'] = macd_line - signal_line # Histogram
    
    # Get latest row
    latest = df.iloc[-1]
    prev = df.iloc[-2]
    
    signal = "HOLD"
    score = 50
    rationale = []
    
    # RSI Logic
    if latest['rsi'] < 30:
        score += 20
        rationale.append("RSI Oversold (<30)")
    elif latest['rsi'] > 70:
        score -= 20
        rationale.append("RSI Overbought (>70)")
        
    # MACD Logic (Crossover)
    if latest['MACDh_12_26_9'] > 0 and prev['MACDh_12_26_9'] <= 0:
        score += 15
        rationale.append("MACD Bullish Crossover")
    elif latest['MACDh_12_26_9'] < 0 and prev['MACDh_12_26_9'] >= 0:
        score -= 15
        rationale.append("MACD Bearish Crossover")
        
    # Determine Signal
    # Lowered thresholds for higher frequency (User Request)
    if score >= 60:
        signal = "BUY"
    elif score <= 40:
        signal = "SELL"
        
    return {
        "symbol": symbol,
        "signal": signal,
        "score": score,
        "rationale": ", ".join(rationale),
        "close_price": latest['close']
    }

def save_signal(signal_data):
    conn = get_db_connection()
    if not conn:
        return

    cursor = conn.cursor()
    sql = """
    INSERT INTO signals (symbol, signal_type, score, rationale)
    VALUES (%s, %s, %s, %s)
    """
    val = (signal_data['symbol'], signal_data['signal'], signal_data['score'], signal_data['rationale'])
    
    try:
        cursor.execute(sql, val)
        conn.commit()
        print(f"Signal saved for {signal_data['symbol']}: {signal_data['signal']}")
    except Exception as e:
        print(f"Error saving signal: {e}")
        
    conn.close()

if __name__ == "__main__":
    # Test
    print(analyze_market("BTCUSDT"))
