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
