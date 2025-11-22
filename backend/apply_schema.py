import mysql.connector
from config import DB_HOST, DB_USER, DB_PASSWORD, DB_NAME

def apply_schema_updates():
    try:
        conn = mysql.connector.connect(
            host=DB_HOST,
            user=DB_USER,
            password=DB_PASSWORD,
            database=DB_NAME
        )
        cursor = conn.cursor()
        
        # Create predictions table
        create_predictions_table = """
        CREATE TABLE IF NOT EXISTS predictions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            symbol VARCHAR(20),
            prediction_time TIMESTAMP, -- The time for which the prediction is made (future)
            predicted_open DECIMAL(20, 8),
            predicted_high DECIMAL(20, 8),
            predicted_low DECIMAL(20, 8),
            predicted_close DECIMAL(20, 8),
            confidence_score DECIMAL(5, 2),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (symbol) REFERENCES coins(symbol)
        );
        """
        cursor.execute(create_predictions_table)
        print("Table 'predictions' checked/created.")
        
        conn.commit()
        conn.close()
        
    except mysql.connector.Error as err:
        print(f"Error applying schema updates: {err}")

if __name__ == "__main__":
    apply_schema_updates()
