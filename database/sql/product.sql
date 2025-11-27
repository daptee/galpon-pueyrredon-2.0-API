CREATE TABLE product_lines (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    status TINYINT(1) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE product_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    status TINYINT(1) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

UPDATE `galpon_pueyrredon`.`product_types` SET `name` = 'Normal' WHERE (`id` = '1');
UPDATE `galpon_pueyrredon`.`product_types` SET `name` = 'Combo' WHERE (`id` = '2');

CREATE TABLE product_furnitures (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    status TINYINT(1) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE product_status (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO product_status (name) VALUES ('Activo'), ('Borrador'), ('Pendiente'), ('Inactivo');

CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    id_product_line INT,
    id_product_type INT,
    id_product_furniture INT,
    places_cant INT,
    volume DECIMAL(10,2),
    description TEXT,
    stock INT,
    product_stock INT,
    show_catalog TINYINT(1) NOT NULL,
    id_product_status INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_product_line) REFERENCES product_lines(id),
    FOREIGN KEY (id_product_type) REFERENCES product_types(id),
    FOREIGN KEY (id_product_furniture) REFERENCES product_furnitures(id),
    FOREIGN KEY (id_product_status) REFERENCES product_status(id),
    FOREIGN KEY (product_stock) REFERENCES products(id)
);

CREATE TABLE products_images (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_product INT,
    image VARCHAR(255) NOT NULL,
    is_main TINYINT(1) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_product) REFERENCES products(id)
);

CREATE TABLE product_products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_parent_product INT,
    id_product INT,
    quantity INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_parent_product) REFERENCES products(id),
    FOREIGN KEY (id_product) REFERENCES products(id)
);

CREATE TABLE product_attributes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    status TINYINT(1) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO product_attributes (name, status) VALUES ('Color', 1), ('Diametro', 1), ('Altura', 1), ('Volumen', 1);

CREATE TABLE product_attributes_values (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_product INT,
    id_product_attribute INT,
    value VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_product) REFERENCES products(id),
    FOREIGN KEY (id_product_attribute) REFERENCES product_attributes(id)
);

CREATE TABLE product_prices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_product INT,
    price DECIMAL(10,2) NOT NULL,
    valid_date_from DATE NOT NULL,
    valid_date_to DATE NOT NULL,
    minimun_quantity INT NOT NULL,
    client_bonification TINYINT(1) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_product) REFERENCES products(id)
);

CREATE TABLE bulk_price_updates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    from_date DATE NOT NULL,
    to_date DATE NOT NULL,
    percentage DECIMAL(5, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

ALTER TABLE product_prices ADD COLUMN id_bulk_update BIGINT UNSIGNED NULL;
ALTER TABLE product_prices ADD FOREIGN KEY (id_bulk_update) REFERENCES bulk_price_updates(id) ON DELETE CASCADE;
