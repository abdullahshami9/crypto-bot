import json
import os
from db_utils import get_db_connection

# Configuration File for Dynamic Parameters
CONFIG_FILE = 'trading_config.json'

def load_config():
    if os.path.exists(CONFIG_FILE):
        with open(CONFIG_FILE, 'r') as f:
            return json.load(f)
    return {"confidence_threshold": 65, "risk_multiplier": 1.0}

def save_config(config):
    with open(CONFIG_FILE, 'w') as f:
        json.dump(config, f, indent=4)

def analyze_performance():
    conn = get_db_connection()
    if not conn: return
    
    cursor = conn.cursor(dictionary=True)
    
    # Get last 50 closed trades
    cursor.execute("SELECT * FROM trades WHERE status = 'CLOSED' ORDER BY exit_time DESC LIMIT 50")
    trades = cursor.fetchall()
    conn.close()
    
    if not trades:
        return
        
    wins = 0
    total_pnl = 0
    
    for trade in trades:
        pnl = float(trade['pnl'] or 0)
        total_pnl += pnl
        if pnl > 0:
            wins += 1
            
    win_rate = (wins / len(trades)) * 100
    print(f"Performance Analysis (Last {len(trades)} trades): Win Rate {win_rate:.1f}%, Total PnL ${total_pnl:.2f}")
    
    # Adaptive Logic
    config = load_config()
    original_conf = config['confidence_threshold']
    
    if win_rate < 40:
        # Performance is poor, be more conservative
        config['confidence_threshold'] = min(85, config['confidence_threshold'] + 2)
        print(f"Win Rate Low. Increasing Confidence Threshold to {config['confidence_threshold']}")
    elif win_rate > 60:
        # Performance is good, can be slightly more aggressive
        config['confidence_threshold'] = max(55, config['confidence_threshold'] - 1)
        print(f"Win Rate High. Decreasing Confidence Threshold to {config['confidence_threshold']}")
        
    if config['confidence_threshold'] != original_conf:
        save_config(config)

if __name__ == "__main__":
    analyze_performance()
