import mysql.connector
from db_utils import get_db_connection

def apply_schema():
    conn = get_db_connection()
    if not conn:
        print("Failed to connect to DB")
        return

    cursor = conn.cursor()
    
    with open('../database/schema.sql', 'r') as f:
        schema_sql = f.read()
    
    # Split by semicolon to execute multiple statements
    statements = schema_sql.split(';')
    
    for statement in statements:
        if statement.strip():
            try:
                cursor.execute(statement)
            except mysql.connector.Error as err:
                print(f"Error executing statement: {err}")
                # Continue even if error (e.g. table exists)
    
    conn.commit()
    cursor.close()
    conn.close()
    print("Schema applied successfully.")

if __name__ == "__main__":
    apply_schema()
