CREATE TABLE budget_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO budget_status (name) VALUES
('Borrador'), ('Enviado'), ('Aprobado'), ('Cancelado'), ('Cerrado');

CREATE TABLE budgets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_budget INT DEFAULT NULL,
    id_client INT NOT NULL,
    client_mail VARCHAR(100),
    client_phone VARCHAR(30),
    id_place INT,
    id_transportation INT,
    date_event DATE,
    time_event TIME,
    days INT,
    quoted_days INT,
    total_price_products DECIMAL(10,2),
    client_bonification DECIMAL(10,2),
    client_bonification_edited DECIMAL(10,2),
    total_bonification VARCHAR(100),
    transportation_cost DECIMAL(10,2),
    transportation_cost_edited DECIMAL(10,2),
    subtotal DECIMAL(10,2),
    iva DECIMAL(10,2),
    total DECIMAL(10,2),
    version_number INT,
    id_budget_status INT,
    products_has_prices BOOLEAN,
    products_has_stock BOOLEAN,
    observations TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_budget_status) REFERENCES budget_status(id)
);

CREATE TABLE budget_pdf_texts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_method TEXT,
    security_deposit TEXT,
    validity_days INT,
    warnings TEXT,
    no_price_products TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE budget_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_budget INT NOT NULL,
    id_product INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2),
    has_stock BOOLEAN,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_budget) REFERENCES budgets(id) ON DELETE CASCADE,
    FOREIGN KEY (id_product) REFERENCES products(id) ON DELETE CASCADE
);

// añadir has_price a budget_products
ALTER TABLE budget_products
ADD has_price BOOLEAN DEFAULT TRUE;

CREATE TABLE products_use_stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_budget INT NOT NULL,
    id_product INT NOT NULL,
    id_product_stock INT NOT NULL,
    date_from DATE,
    date_to DATE,
    quantity INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_budget) REFERENCES budgets(id) ON DELETE CASCADE,
    FOREIGN KEY (id_product) REFERENCES products(id) ON DELETE CASCADE
);

CREATE TABLE budgets_audith (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_budget INT NOT NULL,
    action VARCHAR(100),
    new_budget_status VARCHAR(50),
    observations TEXT,
    user VARCHAR(100),
    date DATE,
    time TIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

ALTER TABLE budgets
ADD volume DECIMAL(10,2) DEFAULT 0;