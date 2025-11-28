import mysql.connector
from db_utils import get_db_connection

def update_schema():
    conn = get_db_connection()
    if not conn:
        print("Failed to connect to DB")
        return

    cursor = conn.cursor()
    
    # Add columns if they don't exist
    try:
        # Signals table
        cursor.execute("SHOW COLUMNS FROM signals LIKE 'llm_analysis'")
        if not cursor.fetchone():
            cursor.execute("ALTER TABLE signals ADD COLUMN llm_analysis TEXT")
            print("Added llm_analysis to signals")
            
        # Trades table
        cursor.execute("SHOW COLUMNS FROM trades LIKE 'atr'")
        if not cursor.fetchone():
            cursor.execute("ALTER TABLE trades ADD COLUMN atr DECIMAL(20, 8)")
            print("Added atr to trades")
            
        conn.commit()
        print("Schema update completed.")
    except Exception as e:
        print(f"Schema update failed: {e}")
    finally:
        conn.close()

if __name__ == "__main__":
    update_schema()
