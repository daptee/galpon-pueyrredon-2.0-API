CREATE TABLE event_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    status INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO event_types (name) VALUES
('Casamiento'),
('Corporativo'),
('Comunión'),
('Cumpleaños'),
('Bioferia'),
('Jornada al aire libre'),
('No especificado');

CREATE TABLE payment_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    status INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO payment_types (name) VALUES
('Pago'),
('Ajuste');

CREATE TABLE payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    status INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO payment_methods (name) VALUES
('Efectivo'),
('Depósito'),
('Transferencia'),
('Cheque');

CREATE TABLE payment_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    status INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO payment_status (name) VALUES
('Aprobado'),
('Anulado');

CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_budget INT NOT NULL,
    id_user INT NOT NULL,
    payment_datetime DATETIME NOT NULL,
    id_payment_type INT NOT NULL,
    id_payment_method INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    observations TEXT,
    id_payment_status INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_budget) REFERENCES budgets(id) ON DELETE CASCADE,
    FOREIGN KEY (id_user) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (id_payment_type) REFERENCES payment_types(id) ON DELETE CASCADE,
    FOREIGN KEY (id_payment_method) REFERENCES payment_methods(id) ON DELETE CASCADE,
    FOREIGN KEY (id_payment_status) REFERENCES payment_status(id) ON DELETE CASCADE
);

CREATE TABLE payment_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_payment INT NOT NULL,
    id_user INT NOT NULL,
    id_payment_status INT NOT NULL,
    datetime DATETIME NOT NULL,
    observations TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_payment) REFERENCES payments(id) ON DELETE CASCADE,
    FOREIGN KEY (id_user) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (id_payment_status) REFERENCES payment_status(id) ON DELETE CASCADE
);

CREATE TABLE budget_delivery_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_budget INT NOT NULL,
    id_event_type INT NOT NULL,
    delivery_options TEXT,
    widthdrawal_options TEXT,
    address VARCHAR(255),
    id_locality INT NOT NULL,
    event_time VARCHAR(100),
    coordination_contact TEXT,
    cellphone_coordination VARCHAR(20),
    reception_contact TEXT,
    cellphone_reception VARCHAR(20),
    additional_delivery_details TEXT,
    additional_order_details TEXT,
    delivery_datetime DATETIME,
    widthdrawal_datetime DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_budget) REFERENCES budgets(id) ON DELETE CASCADE,
    FOREIGN KEY (id_event_type) REFERENCES event_types(id) ON DELETE CASCADE,
    FOREIGN KEY (id_locality) REFERENCES localities(id) ON DELETE CASCADE
);
