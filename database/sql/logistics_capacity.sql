CREATE TABLE logistics_capacity_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    max_daily_events INT NOT NULL DEFAULT 0,
    max_daily_volume DECIMAL(10,2) NOT NULL DEFAULT 0,
    max_successive_events INT NOT NULL DEFAULT 0,
    max_successive_volume DECIMAL(10,2) NOT NULL DEFAULT 0,
    high_demand_threshold DECIMAL(5,2) NOT NULL DEFAULT 60,
    restricted_threshold DECIMAL(5,2) NOT NULL DEFAULT 80,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO logistics_capacity_configs
    (max_daily_events, max_daily_volume, max_successive_events, max_successive_volume)
VALUES (0, 0, 0, 0);

CREATE TABLE blocked_dates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL UNIQUE,
    reason VARCHAR(255) NULL,
    id_user INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_user) REFERENCES users(id)
);
