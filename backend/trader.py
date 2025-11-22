import time
from db_utils import get_db_connection
from config import TRADE_AMOUNT

def execute_trades():
    conn = get_db_connection()
    if not conn:
        return

    cursor = conn.cursor(dictionary=True)
    
    # 1. Check for new signals that haven't been acted upon (conceptually)
    # For simplicity, we'll just look for recent signals and check if we already have an open trade.
    
    cursor.execute("SELECT * FROM signals WHERE created_at > NOW() - INTERVAL 10 MINUTE ORDER BY score DESC")
    signals = cursor.fetchall()
    
    for signal in signals:
        symbol = signal['symbol']
        
        # Check if we have an open trade for this symbol
        cursor.execute("SELECT id FROM trades WHERE symbol = %s AND status = 'OPEN'", (symbol,))
        if cursor.fetchone():
            continue # Already trading this
            
        # Check Portfolio Balance
        cursor.execute("SELECT balance FROM portfolio WHERE id = 1")
        portfolio = cursor.fetchone()
        current_balance = float(portfolio['balance'])
        
        if current_balance >= TRADE_AMOUNT:
            # EXECUTE BUY
            # Get current price
            cursor.execute("SELECT price FROM coins WHERE symbol = %s", (symbol,))
            coin = cursor.fetchone()
            if not coin:
                continue
                
            entry_price = float(coin['price'])
            quantity = TRADE_AMOUNT / entry_price
            
            # Deduct Balance
            new_balance = current_balance - TRADE_AMOUNT
            cursor.execute("UPDATE portfolio SET balance = %s WHERE id = 1", (new_balance,))
            
            # Log Trade
            insert_trade = """
            INSERT INTO trades (symbol, entry_price, quantity, status)
            VALUES (%s, %s, %s, 'OPEN')
            """
            cursor.execute(insert_trade, (symbol, entry_price, quantity))
            conn.commit()
            print(f"Opened Trade for {symbol} at {entry_price}")
        else:
            print("Insufficient funds for new trade.")
            break

    # 2. Manage Open Trades (Exit Logic)
    cursor.execute("SELECT * FROM trades WHERE status = 'OPEN'")
    open_trades = cursor.fetchall()
    
    for trade in open_trades:
        symbol = trade['symbol']
        entry_price = float(trade['entry_price'])
        quantity = float(trade['quantity'])
        trade_id = trade['id']
        
        # Get current price
        cursor.execute("SELECT price FROM coins WHERE symbol = %s", (symbol,))
        coin = cursor.fetchone()
        if not coin:
            continue
            
        current_price = float(coin['price'])
        
        # Simple Exit Logic: Take Profit +2% or Stop Loss -1%
        pnl_percent = (current_price - entry_price) / entry_price
        
        should_close = False
        if pnl_percent >= 0.02:
            print(f"Take Profit triggered for {symbol} (+{pnl_percent*100:.2f}%)")
            should_close = True
        elif pnl_percent <= -0.01:
            print(f"Stop Loss triggered for {symbol} ({pnl_percent*100:.2f}%)")
            should_close = True
            
        if should_close:
            # Close Trade
            exit_value = quantity * current_price
            pnl_amount = exit_value - TRADE_AMOUNT
            
            # Update Trade
            update_trade = """
            UPDATE trades SET exit_price = %s, exit_time = NOW(), status = 'CLOSED', pnl = %s
            WHERE id = %s
            """
            cursor.execute(update_trade, (current_price, pnl_amount, trade_id))
            
            # Refund Portfolio
            cursor.execute("SELECT balance FROM portfolio WHERE id = 1")
            current_balance = float(cursor.fetchone()['balance'])
            new_balance = current_balance + exit_value
            cursor.execute("UPDATE portfolio SET balance = %s WHERE id = 1", (new_balance,))
            
            conn.commit()
            print(f"Closed Trade {symbol}. PnL: ${pnl_amount:.2f}")

    cursor.close()
    conn.close()

if __name__ == "__main__":
    while True:
        execute_trades()
        time.sleep(30) # Run every 30 seconds
