import mysql.connector
from db_utils import create_database_if_not_exists, get_db_connection

def init_db():
    create_database_if_not_exists()
    
    conn = get_db_connection()
    if conn is None:
        print("Failed to connect to database.")
        return

    cursor = conn.cursor()
    
    # Read schema from file
    with open('../database/schema.sql', 'r') as f:
        schema_sql = f.read()
    
    # Execute statements
    statements = schema_sql.split(';')
    for statement in statements:
        if statement.strip():
            try:
                cursor.execute(statement)
            except mysql.connector.Error as err:
                print(f"Error executing statement: {err}")
    
    conn.commit()
    cursor.close()
    conn.close()
    print("Database initialized successfully.")

if __name__ == "__main__":
    init_db()
