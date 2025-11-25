import mysql.connector
from db_utils import get_db_connection

def apply_migration():
    conn = get_db_connection()
    if not conn:
        print("Failed to connect to DB")
        return

    cursor = conn.cursor()
    
    # 1. Create predictions table if not exists
    sql_create = """
    CREATE TABLE IF NOT EXISTS predictions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        symbol VARCHAR(20),
        `interval` VARCHAR(5) DEFAULT '1h',
        prediction_time TIMESTAMP,
        predicted_open DECIMAL(20, 8),
        predicted_high DECIMAL(20, 8),
        predicted_low DECIMAL(20, 8),
        predicted_close DECIMAL(20, 8),
        confidence_score DECIMAL(5, 2),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (symbol) REFERENCES coins(symbol)
    );
    """
    try:
        cursor.execute(sql_create)
        print("Checked/Created predictions table.")
    except Exception as e:
        print(f"Error creating table: {e}")

    # 2. Add interval column if it doesn't exist
    try:
        cursor.execute("SHOW COLUMNS FROM predictions LIKE 'interval'")
        result = cursor.fetchone()
        if not result:
            print("Adding 'interval' column to predictions table...")
            cursor.execute("ALTER TABLE predictions ADD COLUMN `interval` VARCHAR(5) DEFAULT '1h' AFTER symbol")
        else:
            print("'interval' column already exists.")
    except Exception as e:
        print(f"Error altering table: {e}")

    conn.commit()
    conn.close()

if __name__ == "__main__":
    apply_migration()
