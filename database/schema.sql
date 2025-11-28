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
CREATE TABLE IF NOT EXISTS predictions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    symbol VARCHAR(20),
    `interval` VARCHAR(5) DEFAULT '1h',
    prediction_time TIMESTAMP,
    predicted_open DECIMAL(20, 8),
    predicted_high DECIMAL(20, 8),
    predicted_low DECIMAL(20, 8),
    predicted_close DECIMAL(20, 8),
    confidence_score DECIMAL(5, 2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (symbol) REFERENCES coins(symbol)
);
