CREATE TABLE IF NOT EXISTS coins (
    symbol VARCHAR(20) PRIMARY KEY,
    price DECIMAL(20, 8),
    volume DECIMAL(20, 2),
    price_change_24h DECIMAL(10, 2),
    ath DECIMAL(20, 8),
    atl DECIMAL(20, 8),
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS signals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    symbol VARCHAR(20),
    signal_type VARCHAR(10), -- BUY, SELL
    score DECIMAL(5, 2), -- 0-100
    rationale TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (symbol) REFERENCES coins(symbol)
);

CREATE TABLE IF NOT EXISTS portfolio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    balance DECIMAL(20, 2) DEFAULT 1000.00,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS trades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    symbol VARCHAR(20),
    entry_price DECIMAL(20, 8),
    exit_price DECIMAL(20, 8),
    quantity DECIMAL(20, 8),
    pnl DECIMAL(20, 2),
    status VARCHAR(10) DEFAULT 'OPEN', -- OPEN, CLOSED
    entry_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    exit_time TIMESTAMP NULL,
    FOREIGN KEY (symbol) REFERENCES coins(symbol)
);

-- Initialize portfolio if empty
INSERT IGNORE INTO portfolio (id, balance) VALUES (1, 1000.00);

CREATE TABLE IF NOT EXISTS historical_candles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    symbol VARCHAR(20),
    `interval` VARCHAR(5), -- 1h, 4h, 1d
    open DECIMAL(20, 8),
    high DECIMAL(20, 8),
    low DECIMAL(20, 8),
    close DECIMAL(20, 8),
    volume DECIMAL(20, 2),
    close_time TIMESTAMP,
    UNIQUE KEY unique_candle (symbol, `interval`, close_time),
    FOREIGN KEY (symbol) REFERENCES coins(symbol)
);

CREATE TABLE IF NOT EXISTS model_weights (
    feature_name VARCHAR(50) PRIMARY KEY,
    weight DECIMAL(10, 6) DEFAULT 1.0
);

CREATE TABLE IF NOT EXISTS trade_learning (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trade_id INT,
    features_json TEXT,
    reward_score DECIMAL(10, 2),
    learned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trade_id) REFERENCES trades(id)
);
