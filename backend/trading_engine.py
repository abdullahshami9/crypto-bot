from db_utils import get_db_connection
import datetime

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
    if signal['signal'] == "HOLD":
        return

    portfolio = get_portfolio()
    balance = float(portfolio['balance'])
    
    # Risk Management: Use 10% of balance per trade
    trade_amount = balance * 0.10
    
    if trade_amount < 10: # Minimum trade size
        print("Insufficient balance for trade.")
        return

    symbol = signal['symbol']
    price = float(signal['close_price'])
    quantity = trade_amount / price
    
    conn = get_db_connection()
    cursor = conn.cursor()
    
    if signal['signal'] == "BUY":
        # Open a new trade
        print(f"Executing BUY for {symbol} at {price}")
        
        sql = """
        INSERT INTO trades (symbol, entry_price, quantity, status, entry_time)
        VALUES (%s, %s, %s, 'OPEN', NOW())
        """
        cursor.execute(sql, (symbol, price, quantity))
        
        # Deduct from balance (simulated, though we keep balance as 'available' + 'in_trade')
        # For simplicity, we just track 'balance' as total equity or available cash?
        # Let's assume 'balance' is available cash.
        update_balance(-trade_amount)
        
    elif signal['signal'] == "SELL":
        # Close existing open trades for this symbol
        # Find open trades
        cursor.execute("SELECT * FROM trades WHERE symbol = %s AND status = 'OPEN'", (symbol,))
        open_trades = cursor.fetchall() # fetchall returns tuples if not dict cursor
        
        # Re-fetch with dict cursor for easier handling or just use index
        # Let's just use a new cursor or assume tuple: id, symbol, entry, exit, qty, pnl, status...
        # Actually, let's just use dictionary cursor for safety
        cursor.close()
        cursor = conn.cursor(dictionary=True)
        cursor.execute("SELECT * FROM trades WHERE symbol = %s AND status = 'OPEN'", (symbol,))
        open_trades = cursor.fetchall()
        
        for trade in open_trades:
            entry_price = float(trade['entry_price'])
            qty = float(trade['quantity'])
            pnl = (price - entry_price) * qty
            
            print(f"Closing trade for {symbol}. PnL: {pnl}")
            
            # Update trade
            sql = """
            UPDATE trades 
            SET exit_price = %s, exit_time = NOW(), status = 'CLOSED', pnl = %s
            WHERE id = %s
            """
            cursor.execute(sql, (price, pnl, trade['id']))
            
            # Return capital + pnl to balance
            return_amount = (entry_price * qty) + pnl
            update_balance(return_amount)

    conn.commit()
    conn.close()

def check_stop_loss_take_profit(current_prices):
    # current_prices: dict {symbol: price}
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
            pnl_pct = (current_price - entry_price) / entry_price
            
            # SL 2%, TP 5%
            if pnl_pct <= -0.02 or pnl_pct >= 0.05:
                print(f"SL/TP hit for {symbol}. PnL%: {pnl_pct*100:.2f}%")
                
                pnl = (current_price - entry_price) * qty
                
                sql = """
                UPDATE trades 
                SET exit_price = %s, exit_time = NOW(), status = 'CLOSED', pnl = %s
                WHERE id = %s
                """
                cursor.execute(sql, (current_price, pnl, trade['id']))
                
                return_amount = (entry_price * qty) + pnl
                update_balance(return_amount)
                conn.commit()

    conn.close()
