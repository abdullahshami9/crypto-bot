import pandas as pd
import pandas_ta as ta
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
    df = pd.read_sql(query, conn)
    conn.close()
    
    if df.empty:
        return None
        
    # Ensure numeric types
    cols = ['open', 'high', 'low', 'close', 'volume']
    df[cols] = df[cols].apply(pd.to_numeric)
    return df

def analyze_market(symbol):
    df = get_historical_data(symbol)
    if df is None or len(df) < 30:
        return None

    # Calculate Indicators
    # RSI
    df['rsi'] = ta.rsi(df['close'], length=14)
    
    # MACD
    macd = ta.macd(df['close'])
    df = pd.concat([df, macd], axis=1)
    
    # Bollinger Bands
    bb = ta.bbands(df['close'])
    df = pd.concat([df, bb], axis=1)
    
    # Get latest row
    latest = df.iloc[-1]
    prev = df.iloc[-2]
    
    signal = "HOLD"
    score = 50
    rationale = []
    
    # Simple Logic (Placeholder for ML)
    
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
    if score >= 75:
        signal = "BUY"
    elif score <= 25:
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
