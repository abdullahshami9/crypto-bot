from db_utils import get_db_connection

def update_schema():
    conn = get_db_connection()
    if not conn:
        print("Failed to connect to database.")
        return

    cursor = conn.cursor()
    try:
        # 1. Add Binance Keys to Users
        print("Adding Binance keys to users...")
        try:
            cursor.execute("ALTER TABLE users ADD COLUMN binance_api_key VARCHAR(255) NULL")
            cursor.execute("ALTER TABLE users ADD COLUMN binance_secret_key VARCHAR(255) NULL")
        except Exception as e:
            print(f"Columns might already exist: {e}")

        # 2. Add user_id to Portfolio
        print("Adding user_id to portfolio...")
        try:
            cursor.execute("ALTER TABLE portfolio ADD COLUMN user_id INT")
            cursor.execute("ALTER TABLE portfolio ADD CONSTRAINT fk_portfolio_user FOREIGN KEY (user_id) REFERENCES users(id)")
        except Exception as e:
            print(f"Column might already exist: {e}")

        # 3. Add portfolio_id to Trades
        print("Adding portfolio_id to trades...")
        try:
            cursor.execute("ALTER TABLE trades ADD COLUMN portfolio_id INT")
            cursor.execute("ALTER TABLE trades ADD CONSTRAINT fk_trades_portfolio FOREIGN KEY (portfolio_id) REFERENCES portfolio(id)")
        except Exception as e:
            print(f"Column might already exist: {e}")

        # 4. Migration: Link existing Portfolio (id=1) to Admin (id=1)
        # Assuming Admin is ID 1. If not, we might need to find it.
        print("Migrating data...")
        cursor.execute("SELECT id FROM users WHERE role = 'admin' LIMIT 1")
        admin = cursor.fetchone()
        if admin:
            admin_id = admin[0]
            print(f"Found Admin ID: {admin_id}")
            
            # Link Portfolio 1 to Admin
            cursor.execute("UPDATE portfolio SET user_id = %s WHERE id = 1", (admin_id,))
            
            # Link all existing trades to Portfolio 1
            cursor.execute("UPDATE trades SET portfolio_id = 1 WHERE portfolio_id IS NULL")
            
            conn.commit()
            print("Migration successful.")
        else:
            print("Admin user not found. Please create an admin user first.")

    except Exception as e:
        print(f"Critical Error: {e}")
    finally:
        conn.close()

if __name__ == "__main__":
    update_schema()
