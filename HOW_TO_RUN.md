# How to Run the Crypto Intelligence System

## Prerequisites
1.  **XAMPP**: Ensure Apache and MySQL are running.
2.  **Python**: Ensure Python is installed. On Windows, we use the `py` launcher.

## Setup
1.  **Database**:
    - The system uses the `crypto_intelligence` database.
    - The schema has been applied automatically.

2.  **Dependencies**:
    - Open a terminal in `backend/`.
    - Run:
      ```bash
      py -m pip install -r requirements.txt
      ```

## Running the System
1.  **Start the Backend (AI & Trading Engine)**:
    - Open a terminal in `backend/`.
    - Run:
      ```bash
      py main.py
      py -3.13 main.py
      ```
    - **Note**: If `python` command doesn't work, always use `py`.
    - You should see logs indicating "Fetching Market Data", "Analyzing", etc.

2.  **View the Dashboard**:
    - Open your browser and go to:
      `http://localhost/crypto_intelligence/public/index.php`
    - (Adjust the URL if your XAMPP document root is different).

## Troubleshooting
- **SQL Errors**: If you see JSON errors, the schema has been updated to use TEXT for compatibility. Run `py apply_schema.py` again if needed.
- **Missing Modules**: Run the pip install command again.
