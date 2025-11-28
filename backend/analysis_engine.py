import pandas as pd
import pandas_ta as ta
import warnings
warnings.filterwarnings('ignore')
from db_utils import get_db_connection
from config import GEMINI_API_KEY, LLM_MODEL
import json
import google.generativeai as genai
import os

# Configure LLM
if GEMINI_API_KEY:
    genai.configure(api_key=GEMINI_API_KEY)
else:
    print("WARNING: GEMINI_API_KEY not found. LLM features disabled.")

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
        
    cols = ['open', 'high', 'low', 'close', 'volume']
    df[cols] = df[cols].apply(pd.to_numeric)
    return df

def calculate_advanced_indicators(df):
    """
    Calculates advanced technical indicators using pandas_ta.
    """
    # Bollinger Bands
    bb = df.ta.bbands(length=20, std=2)
    df = pd.concat([df, bb], axis=1)
    
    # RSI
    df['rsi'] = df.ta.rsi(length=14)
    
    # MACD
    macd = df.ta.macd(fast=12, slow=26, signal=9)
    df = pd.concat([df, macd], axis=1)
    
    # ATR (for volatility/risk management)
    df['atr'] = df.ta.atr(length=14)
    
    # ADX (Trend Strength)
    adx = df.ta.adx(length=14)
    df = pd.concat([df, adx], axis=1)
    
    # EMAs
    df['ema_50'] = df.ta.ema(length=50)
    df['ema_200'] = df.ta.ema(length=200)
    
    return df

def get_llm_analysis(symbol, df, indicators):
    """
    Uses Google Gemini to analyze market data and provide a prediction.
    """
    if not GEMINI_API_KEY:
        return None

    try:
        model = genai.GenerativeModel(LLM_MODEL)
        
        # Prepare Context
        latest = df.iloc[-1]
        prev = df.iloc[-2]
        
        context = f"""
        Symbol: {symbol}
        Current Price: {latest['close']}
        
        Technical Indicators:
        - RSI (14): {latest['rsi']:.2f}
        - MACD Line: {latest['MACD_12_26_9']:.2f} (Signal: {latest['MACDs_12_26_9']:.2f})
        - ATR (14): {latest['atr']:.2f}
        - ADX (14): {latest['ADX_14']:.2f}
        - EMA 50: {latest['ema_50']:.2f}
        - EMA 200: {latest['ema_200']:.2f}
        - Bollinger Upper: {latest['BBU_20_2.0']:.2f}
        - Bollinger Lower: {latest['BBL_20_2.0']:.2f}
        
        Recent Price Action (Last 5 candles):
        {df.tail(5)[['close', 'volume']].to_string()}
        """
        
        prompt = f"""
        You are a Senior Crypto Trading Analyst with 20 years of experience. 
        Analyze the following technical data for {symbol} and provide a trading signal.
        
        {context}
        
        Rules:
        1. Focus on confluence (e.g., RSI oversold + Support bounce).
        2. Consider Trend (EMA 50 vs 200) and Volatility (ATR).
        3. Provide a Confidence Score (0-100).
        4. Output strictly in JSON format.
        
        JSON Format:
        {{
            "signal": "BUY" or "SELL" or "HOLD",
            "confidence": <int>,
            "reasoning": "<concise explanation>"
        }}
        """
        
        response = model.generate_content(prompt)
        text = response.text.strip()
        
        # Clean markdown if present
        if text.startswith("```json"):
            text = text[7:-3]
        
        return json.loads(text)
        
    except Exception as e:
        print(f"LLM Analysis Failed: {e}")
        return None

def analyze_market(symbol, current_price=None):
    df = get_historical_data(symbol)
    if df is None or len(df) < 200: # Need 200 for EMA
        return None

    # Update latest candle
    if current_price is not None:
        df.iloc[-1, df.columns.get_loc('close')] = float(current_price)
        if float(current_price) > df.iloc[-1]['high']:
            df.iloc[-1, df.columns.get_loc('high')] = float(current_price)
        if float(current_price) < df.iloc[-1]['low']:
            df.iloc[-1, df.columns.get_loc('low')] = float(current_price)

    # Calculate Indicators
    df = calculate_advanced_indicators(df)
    latest = df.iloc[-1]
    
    # Technical Score Calculation
    tech_score = 50
    rationale = []
    
    # Trend
    if latest['close'] > latest['ema_200']:
        tech_score += 10
        rationale.append("Above EMA200 (Bullish Trend)")
    else:
        tech_score -= 10
        rationale.append("Below EMA200 (Bearish Trend)")
        
    # RSI
    if latest['rsi'] < 30:
        tech_score += 15
        rationale.append("RSI Oversold")
    elif latest['rsi'] > 70:
        tech_score -= 15
        rationale.append("RSI Overbought")
        
    # MACD
    if latest['MACDh_12_26_9'] > 0:
        tech_score += 10
    else:
        tech_score -= 10
        
    # ADX (Trend Strength)
    if latest['ADX_14'] > 25:
        rationale.append("Strong Trend (ADX>25)")
    else:
        rationale.append("Weak Trend")

    # LLM Analysis
    llm_result = get_llm_analysis(symbol, df, latest)
    llm_score = 50
    llm_reasoning = "LLM Unavailable"
    
    if llm_result:
        llm_conf = llm_result.get('confidence', 50)
        llm_sig = llm_result.get('signal', 'HOLD')
        llm_reasoning = llm_result.get('reasoning', '')
        
        if llm_sig == 'BUY':
            llm_score = 50 + (llm_conf / 2)
        elif llm_sig == 'SELL':
            llm_score = 50 - (llm_conf / 2)
    
    # Final Weighted Score (60% Tech, 40% LLM)
    final_score = (tech_score * 0.6) + (llm_score * 0.4)
    
    signal = "HOLD"
    if final_score >= 65:
        signal = "BUY"
    elif final_score <= 35:
        signal = "SELL"
        
    return {
        "symbol": symbol,
        "signal": signal,
        "score": final_score,
        "rationale": ", ".join(rationale),
        "llm_analysis": llm_reasoning,
        "close_price": latest['close'],
        "atr": latest['atr']
    }

def save_signal(signal_data):
    conn = get_db_connection()
    if not conn: return

    cursor = conn.cursor()
    sql = """
    INSERT INTO signals (symbol, signal_type, score, rationale, llm_analysis)
    VALUES (%s, %s, %s, %s, %s)
    """
    val = (
        signal_data['symbol'], 
        signal_data['signal'], 
        signal_data['score'], 
        signal_data['rationale'],
        signal_data.get('llm_analysis', '')
    )
    
    try:
        cursor.execute(sql, val)
        conn.commit()
        print(f"Signal saved for {signal_data['symbol']}: {signal_data['signal']} (Score: {signal_data['score']:.1f})")
    except Exception as e:
        print(f"Error saving signal: {e}")
        
    conn.close()

if __name__ == "__main__":
    print(analyze_market("BTCUSDT"))
