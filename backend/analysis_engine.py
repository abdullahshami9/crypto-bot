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

def analyze_market(symbol):
    df = get_historical_data(symbol)
    if df is None or len(df) < 30:
        return None

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
    
    # Add to dataframe (using same column names as before for compatibility if needed, or just using variables)
    df['MACDh_12_26_9'] = macd_line - signal_line # Histogram
    
    # Bollinger Bands
    sma20 = df['close'].rolling(window=20).mean()
    std20 = df['close'].rolling(window=20).std()
    # df['BBL_20_2.0'] = sma20 - 2 * std20
    # df['BBU_20_2.0'] = sma20 + 2 * std20
    # We only need to append them if we use them later, but the logic below doesn't seem to explicitly use BB columns for signal, 
    # just RSI and MACD. The original code concatenated them.
    # For safety, let's keep the dataframe structure clean.
    
    # Remove pandas_ta import at the top if not done yet

    
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
