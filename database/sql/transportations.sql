CREATE TABLE transportations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    load_volume_up DECIMAL(8,2) NOT NULL,
    schedule_cost INT(11) NOT NULL,
    cost_km INT(11) NOT NULL,
    charge_discharge_time INT(4) NOT NULL,
    minimum_quantity INT(3) NOT NULL,
    pawn_quantity INT(3) NOT NULL,
    status INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);