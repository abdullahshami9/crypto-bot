from db_utils import get_db_connection
conn = get_db_connection()
cursor = conn.cursor()
cursor.execute("SELECT COUNT(*) FROM historical_candles")
print(f"Candles: {cursor.fetchone()[0]}")
conn.close()
