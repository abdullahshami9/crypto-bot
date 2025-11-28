import mysql.connector
from db_utils import get_db_connection

conn = get_db_connection()
cursor = conn.cursor(dictionary=True)

print("--- Active Trades ---")
cursor.execute("SELECT t.*, c.price as current_price FROM trades t LEFT JOIN coins c ON t.symbol = c.symbol WHERE t.status = 'OPEN' ORDER BY t.entry_time DESC")
trades = cursor.fetchall()
for t in trades:
    print(t)

print("\n--- Portfolio ---")
cursor.execute("SELECT * FROM portfolio WHERE id = 1")
print(cursor.fetchone())

conn.close()
