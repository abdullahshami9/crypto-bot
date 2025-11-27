import mysql.connector
from config import DB_HOST, DB_USER, DB_PASSWORD, DB_NAME

def get_db_connection():
    try:
        conn = mysql.connector.connect(
            host=DB_HOST,
            user=DB_USER,
            password=DB_PASSWORD,
            database=DB_NAME
        )
        return conn
    except mysql.connector.Error as err:
        print(f"Error connecting to database: {err}")
        return None

def create_database_if_not_exists():
    try:
        conn = mysql.connector.connect(
            host=DB_HOST,
            user=DB_USER,
            password=DB_PASSWORD
        )
        cursor = conn.cursor()
        cursor.execute(f"CREATE DATABASE IF NOT EXISTS {DB_NAME}")
        conn.close()
        print(f"Database {DB_NAME} checked/created.")
    except mysql.connector.Error as err:
        print(f"Error creating database: {err}")

def create_error_log_table():
    conn = get_db_connection()
    if not conn: return
    cursor = conn.cursor()
    try:
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS generic_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                function_name VARCHAR(255),
                file_name VARCHAR(255),
                ip_address VARCHAR(50),
                query_text TEXT,
                error_message TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        """)
        conn.commit()
    except mysql.connector.Error as err:
        print(f"Error creating generic_logs table: {err}")
    finally:
        conn.close()

def log_db_error(file_name, function_name, error_message, query_text=None, ip_address='localhost'):
    conn = get_db_connection()
    if not conn: return
    cursor = conn.cursor()
    try:
        sql = """
            INSERT INTO generic_logs (file_name, function_name, error_message, query_text, ip_address)
            VALUES (%s, %s, %s, %s, %s)
        """
        cursor.execute(sql, (file_name, function_name, str(error_message), query_text, ip_address))
        conn.commit()
        print(f"Error logged to generic_logs: {error_message}")
    except mysql.connector.Error as err:
        print(f"Failed to log error: {err}")
    finally:
        conn.close()

# Initialize logs table on import
create_error_log_table()
