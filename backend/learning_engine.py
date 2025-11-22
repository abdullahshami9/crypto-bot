from db_utils import get_db_connection
import json

def review_trades():
    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)
    
    # Find closed trades that haven't been learned from yet
    # We need a way to track this. Let's check 'trade_learning' table.
    sql = """
    SELECT t.* FROM trades t
    LEFT JOIN trade_learning tl ON t.id = tl.trade_id
    WHERE t.status = 'CLOSED' AND tl.id IS NULL
    """
    cursor.execute(sql)
    trades_to_learn = cursor.fetchall()
    
    if not trades_to_learn:
        conn.close()
        return

    print(f"Learning from {len(trades_to_learn)} trades...")

    for trade in trades_to_learn:
        pnl = float(trade['pnl'])
        
        # Determine reward score
        reward = 0
        if pnl > 0:
            reward = 1 # Positive reinforcement
        else:
            reward = -1 # Negative reinforcement
            
        # In a real ML system, we would update weights here.
        # For this MVP, we'll just log the learning event and maybe adjust a global "confidence" multiplier
        # or specific feature weights if we tracked which features triggered the trade.
        
        # Let's assume we tracked the rationale in 'signals' table, but we need to link signal to trade.
        # For now, we'll just store the PnL as the reward.
        
        features = {
            "pnl": pnl,
            "symbol": trade['symbol'],
            "entry_price": float(trade['entry_price']),
            "exit_price": float(trade['exit_price'])
        }
        
        sql_insert = """
        INSERT INTO trade_learning (trade_id, features_json, reward_score)
        VALUES (%s, %s, %s)
        """
        cursor.execute(sql_insert, (trade['id'], json.dumps(features), reward))
        
        # Update model weights (Simulated)
        # e.g. if RSI was used, increase RSI weight.
        # Since we don't have the signal link easily here without a join, we'll skip specific weight updates
        # and just say we "learned".
        
    conn.commit()
    conn.close()
    print("Learning cycle complete.")

if __name__ == "__main__":
    review_trades()
