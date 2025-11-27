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
            'defaultType': 'future', # Use futures for shorting support, or 'spot'
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
    Signal dict: {symbol, signal (BUY/SELL), close_price, confidence, reasoning}
    """
    if signal['signal'] == "HOLD":
        return

    portfolio = get_portfolio()
    balance = float(portfolio['balance'])
    
    # Dynamic Position Sizing based on Confidence
    confidence = signal.get('confidence', 50)
    size_multiplier = 1.0
    if confidence > 80: size_multiplier = 1.5
    elif confidence < 60: size_multiplier = 0.5
    
    trade_amount = balance * TRADE_AMOUNT_PCT * size_multiplier
    
    if trade_amount < 10: 
        print("Insufficient balance/Trade size too small.")
        return

    symbol = signal['symbol']
    price = float(signal['close_price'])
    quantity = trade_amount / price
    
    conn = get_db_connection()
    cursor = conn.cursor()
    
    # Check for existing open trades for this symbol
    # Use fetchall to consume all results and prevent "Unread result found"
    cursor.execute("SELECT * FROM trades WHERE symbol = %s AND status = 'OPEN'", (symbol,))
    rows = cursor.fetchall()
    existing_trade = rows[0] if rows else None
    
    if existing_trade:
        # If we have an open trade, check if we need to close it (reversal)
        # For simplicity: If Signal is SELL and we have a BUY (Long) trade, close it.
        # Assuming 'BUY' = Long, 'SELL' = Short for now.
        # But if we are just Spot trading, SELL means close.
        # Let's assume this is a Long-Only Spot Bot for safety unless Futures is explicitly requested.
        # User asked for "take trades in future", so let's assume Futures logic (Long/Short).
        
        # Logic:
        # If Signal is BUY and we are Short -> Close Short, Open Long
        # If Signal is SELL and we are Long -> Close Long, Open Short
        # If Signal matches current position -> Add to position? (Skip for now)
        pass 
        # For this iteration, let's keep it simple: Close if signal opposes current trade.
    
    # Execute New Trade
    print(f"Executing {signal['signal']} for {symbol} at {price} (Conf: {confidence}%)")
    
    # In a real scenario, we would call exchange.create_order(...) here
    # order = exchange.create_order(symbol, 'market', side, quantity)
    
    sql = """
    INSERT INTO trades (symbol, entry_price, quantity, status, entry_time, type, reasoning)
    VALUES (%s, %s, %s, 'OPEN', NOW(), %s, %s)
    """
    trade_type = 'LONG' if signal['signal'] == 'BUY' else 'SHORT'
    
    cursor.execute(sql, (symbol, price, quantity, trade_type, signal.get('reasoning', '')))
    
    # Deduct margin (simulated)
    update_balance(-trade_amount)
    
    conn.commit()
    conn.close()

def check_risk_management(current_prices):
    """
    Monitors open trades for SL/TP.
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
            trade_type = trade.get('type', 'LONG') # Default to LONG if column missing
            
            # Calculate PnL
            if trade_type == 'LONG':
                pnl_pct = (current_price - entry_price) / entry_price
            else: # SHORT
                pnl_pct = (entry_price - current_price) / entry_price
            
            # Check SL/TP
            close_trade = False
            reason = ""
            
            if pnl_pct <= -STOP_LOSS_PCT:
                close_trade = True
                reason = "Stop Loss Hit"
            elif pnl_pct >= TAKE_PROFIT_PCT:
                close_trade = True
                reason = "Take Profit Hit"
                
            if close_trade:
                print(f"{reason} for {symbol}. PnL: {pnl_pct*100:.2f}%")
                
                pnl_amount = (entry_price * qty) * pnl_pct # Approx PnL value
                
                sql = """
                UPDATE trades 
                SET exit_price = %s, exit_time = NOW(), status = 'CLOSED', pnl = %s, exit_reason = %s
                WHERE id = %s
                """
                
                # Use a new cursor or ensure the update is safe. 
                # Since we are iterating a fetched list, using the same cursor for update is fine if we don't have pending results.
                # But to be safe, let's use a fresh cursor for the update or just execute on the same one since fetchall cleared it.
                cursor.execute(sql, (current_price, pnl_amount, reason, trade['id']))
                
                # Return margin + PnL
                update_balance((entry_price * qty) + pnl_amount)
                conn.commit()

    conn.close()
