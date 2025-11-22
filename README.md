# Virtual Crypto Trading Engine - Setup Guide

## Prerequisites
- Python 3.8+
- PHP 7.4+
- MySQL Database

## Installation

1.  **Database Setup**
    -   Ensure MySQL is running.
    -   Create a database named `crypto_engine` (or let the script do it).
    -   Update `backend/config.py` and `includes/db.php` with your MySQL credentials if they differ from default (`root`, no password).
    -   Run the initialization script:
        ```bash
        cd backend
        python init_db.py
        ```

2.  **Install Python Dependencies**
    ```bash
    pip install -r backend/requirements.txt
    ```

## Running the System

You need to run three components simultaneously (use separate terminals):

1.  **Data Ingestion (Background)**
    ```bash
    cd backend
    python data_ingest.py
    ```

2.  **Signal & Trading Engine (Background)**
    ```bash
    cd backend
    python signal_engine.py
    # In another terminal:
    python trader.py
    ```
    *(Note: You can combine these or run them in a process manager)*

3.  **Web Dashboard**
    ```bash
    cd public
    php -S localhost:8000
    ```

## Usage
-   Open your browser to `http://localhost:8000`.
-   Watch as the system pulls data, generates signals, and executes virtual trades.
