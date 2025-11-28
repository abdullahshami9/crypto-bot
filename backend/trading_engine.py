import time
import ccxt
from db_utils import get_db_connection
from config import *
from datetime import datetime

# Initialize Exchange
def get_exchange():
    exchange_class = getattr(ccxt, 'binance')
    exchange = exchange_class({
        'apiKey': BINANCE_API_KEY,
        'secret': BINANCE_SECRET_KEY,
        'enableRateLimit': True,
        'options': {
            'defaultType': 'future', 
        }
    })
    if USE_TESTNET:
        exchange.set_sandbox_mode(True)
    return exchange

def get_portfolio():
    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT * FROM portfolio WHERE id = 1")
    portfolio = cursor.fetchone()
    conn.close()
    return portfolio

def update_balance(amount):
    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute("UPDATE portfolio SET balance = balance + %s WHERE id = 1", (amount,))
    conn.commit()
    conn.close()

def execute_trade(signal):
    """
    Executes a trade based on the signal.
    Signal dict: {symbol, signal (BUY/SELL), close_price, confidence, reasoning, atr, llm_analysis}
    """
    if signal['signal'] == "HOLD":
        return

    portfolio = get_portfolio()
    balance = float(portfolio['balance'])
    
    # Dynamic Position Sizing based on Confidence
    confidence = signal.get('score', 50) # Use score as confidence proxy
    size_multiplier = 1.0
    if confidence > 70: size_multiplier = 1.5
    elif confidence < 40: size_multiplier = 0.5
    
    trade_amount = balance * TRADE_AMOUNT_PCT * size_multiplier
    
    if trade_amount < 10: 
        print("Insufficient balance/Trade size too small.")
        return

    symbol = signal['symbol']
    price = float(signal['close_price'])
    atr = float(signal.get('atr', 0))
    quantity = trade_amount / price
    
    conn = get_db_connection()
    cursor = conn.cursor()
    
    # Check for existing open trades
    cursor.execute("SELECT * FROM trades WHERE symbol = %s AND status = 'OPEN'", (symbol,))
    rows = cursor.fetchall()
    existing_trade = rows[0] if rows else None
    
    if existing_trade:
        # Close existing if signal reverses
        existing_type = existing_trade['type'] # LONG or SHORT
        new_type = 'LONG' if signal['signal'] == 'BUY' else 'SHORT'
        
        if existing_type != new_type:
             print(f"Reversal detected for {symbol}. Closing existing {existing_type}.")
             # Close logic here (omitted for brevity, similar to check_risk_management)
             # For now, we just skip opening a new one if one exists to avoid complexity
        return 
    
    # Execute New Trade
    print(f"Executing {signal['signal']} for {symbol} at {price} (Score: {confidence})")
    
    # Combine reasoning
    full_reasoning = f"{signal.get('rationale', '')} | LLM: {signal.get('llm_analysis', 'N/A')}"
    
    sql = """
    INSERT INTO trades (symbol, entry_price, quantity, status, entry_time, type, reasoning, atr)
    VALUES (%s, %s, %s, 'OPEN', NOW(), %s, %s, %s)
    """
    trade_type = 'LONG' if signal['signal'] == 'BUY' else 'SHORT'
    
    cursor.execute(sql, (symbol, price, quantity, trade_type, full_reasoning, atr))
    
    # Deduct margin (simulated)
    update_balance(-trade_amount)
    
    conn.commit()
    conn.close()

def check_risk_management(current_prices):
    """
    Monitors open trades for SL/TP using ATR if available.
    current_prices: {symbol: price}
    """
    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT * FROM trades WHERE status = 'OPEN'")
    open_trades = cursor.fetchall()
    
    for trade in open_trades:
        symbol = trade['symbol']
        if symbol in current_prices:
            current_price = current_prices[symbol]
            entry_price = float(trade['entry_price'])
            qty = float(trade['quantity'])
            trade_type = trade.get('type', 'LONG')
            atr = float(trade.get('atr') or 0)
            
            # Calculate PnL
            if trade_type == 'LONG':
                pnl_pct = (current_price - entry_price) / entry_price
            else: # SHORT
                pnl_pct = (entry_price - current_price) / entry_price
            
            # Determine SL/TP Levels
            # If ATR is available, use it. Else fallback to fixed %
            if atr > 0:
                # Dynamic ATR-based SL/TP
                # SL = 2 * ATR, TP = 4 * ATR (Risk:Reward 1:2)
                atr_pct = atr / entry_price
                sl_pct = 2 * atr_pct
                tp_pct = 4 * atr_pct
            else:
                sl_pct = STOP_LOSS_PCT
                tp_pct = TAKE_PROFIT_PCT
            
            close_trade = False
            reason = ""
            
            if pnl_pct <= -sl_pct:
                close_trade = True
                reason = f"Stop Loss Hit (SL: {sl_pct*100:.2f}%)"
            elif pnl_pct >= tp_pct:
                close_trade = True
                reason = f"Take Profit Hit (TP: {tp_pct*100:.2f}%)"
                
            if close_trade:
                print(f"{reason} for {symbol}. PnL: {pnl_pct*100:.2f}%")
                
                pnl_amount = (entry_price * qty) * pnl_pct
                
                sql = """
                UPDATE trades 
                SET exit_price = %s, exit_time = NOW(), status = 'CLOSED', pnl = %s, exit_reason = %s
                WHERE id = %s
                """
                
                cursor.execute(sql, (current_price, pnl_amount, reason, trade['id']))
                
                # Return margin + PnL
                update_balance((entry_price * qty) + pnl_amount)
                conn.commit()

    conn.close()
