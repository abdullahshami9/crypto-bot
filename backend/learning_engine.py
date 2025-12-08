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
            
    win_rate = (wins / len(trades)) * 100 if trades else 0
    print(f"Performance Analysis (Last {len(trades)} trades): Win Rate {win_rate:.1f}%, Total PnL ${total_pnl:.2f}")

    # Check Prediction Accuracy
    pred_success = 0
    try:
        import prediction_engine
        prediction_engine.check_predictions() # Run verification step
        pred_success = prediction_engine.get_success_ratio()
        print(f"Prediction Success Ratio (Last 100): {pred_success:.1f}%")
    except Exception as e:
        print(f"Warning: Could not fetch prediction stats: {e}")

    # Adaptive Logic
    config = load_config()
    original_conf = config.get('confidence_threshold', 65)
    
    # Combined score (weighted average)
    # If no trades, rely 100% on prediction success
    if not trades:
        combined_score = pred_success
    else:
        combined_score = (win_rate * 0.4) + (pred_success * 0.6) # Give more weight to AI prediction accuracy
        
    print(f"Combined Learning Score: {combined_score:.1f}")

    if combined_score < 40 and combined_score > 0:
        # Performance is poor, be more conservative
        config['confidence_threshold'] = min(85, original_conf + 5)
        print(f"Score Low. Increasing Confidence Threshold to {config['confidence_threshold']}")
    elif combined_score > 70:
        # Performance is good, can be slightly more aggressive
        config['confidence_threshold'] = max(55, original_conf - 2)
        print(f"Score High. Decreasing Confidence Threshold to {config['confidence_threshold']}")
        
    if config.get('confidence_threshold') != original_conf:
        save_config(config)

if __name__ == "__main__":
    analyze_performance()
