import time
from db_utils import get_db_connection

def analyze_market():
    conn = get_db_connection()
    if not conn:
        return

    cursor = conn.cursor(dictionary=True)
    
    # Fetch coins that are interesting
    # Criteria: 
    # 1. High Volatility (using price_change_24h as a proxy for now)
    # 2. Near ATL (price is within X% of ATL) - logic simplified for MVP
    # 3. Liquid (Volume > 1M)
    
    query = """
    SELECT * FROM coins 
    WHERE volume > 1000000 
    AND price_change_24h > -10 AND price_change_24h < 10 -- Stable-ish or trending
    """
    
    cursor.execute(query)
    coins = cursor.fetchall()
    
    print(f"Analyzing {len(coins)} coins for signals...")
    
    for coin in coins:
        symbol = coin['symbol']
        price = float(coin['price'])
        atl = float(coin['atl'])
        
        # Logic: If price is close to ATL (e.g. within 5-10%) and showing signs of life (positive change?)
        # For this demo, let's say if price is < 1.1 * ATL (within 10% of ATL)
        # AND price_change_24h > 0 (starting to recover)
        
        # Note: Since we just populated ATL with current price in data_ingest for new coins, 
        # this logic might trigger easily. In a real scenario, ATL would be historical.
        # Let's add a random factor or stricter logic to avoid spamming signals in this demo.
        
        score = 0
        rationale = []
        
        # 1. ATL Proximity Score
        if atl > 0:
            proximity = (price - atl) / atl
            if proximity < 0.05: # Very close to ATL
                score += 40
                rationale.append("Near ATL (<5%)")
            elif proximity < 0.15:
                score += 20
                rationale.append("Near ATL (<15%)")
        
        # 2. Volatility/Momentum Score
        change = float(coin['price_change_24h'])
        if change > 5:
            score += 30
            rationale.append("Strong Momentum (>5%)")
        elif change > 0:
            score += 10
            rationale.append("Positive Momentum")
            
        # 3. Liquidity Score (Bonus)
        if float(coin['volume']) > 10000000: # > 10M
            score += 10
            rationale.append("High Liquidity")
            
        if score >= 50:
            # Check if we already have a recent signal to avoid duplicates
            check_sql = "SELECT id FROM signals WHERE symbol = %s AND created_at > NOW() - INTERVAL 1 HOUR"
            cursor.execute(check_sql, (symbol,))
            if not cursor.fetchone():
                print(f"Generated Signal for {symbol} (Score: {score})")
                insert_sql = "INSERT INTO signals (symbol, signal_type, score, rationale) VALUES (%s, 'BUY', %s, %s)"
                cursor.execute(insert_sql, (symbol, score, ", ".join(rationale)))
                conn.commit()

    cursor.close()
    conn.close()

if __name__ == "__main__":
    while True:
        analyze_market()
        time.sleep(60) # Run every minute
