CREATE TABLE clients_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    status INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insertar registros iniciales en "clients_types"
INSERT INTO clients_types (name, status) VALUES
('Prospecto', 1),
('Recurrente', 1);

-- Crear tabla "clients_classes"
CREATE TABLE clients_classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    status INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insertar registros iniciales en "clients_classes"
INSERT INTO clients_classes (name, status) VALUES
('Particular', 1),
('Empresa', 1);

-- Crear tabla "clients"
CREATE TABLE clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_client_type INT NOT NULL,
    id_client_class INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    lastname VARCHAR(100),
    mail VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(15),
    cuit VARCHAR(15),
    address VARCHAR(255),
    bonus_percentage DECIMAL(5, 2) DEFAULT 0,
    status INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_client_type) REFERENCES clients_types(id) ON DELETE CASCADE,
    FOREIGN KEY (id_client_class) REFERENCES clients_classes(id) ON DELETE CASCADE
);

-- Crear tabla "clients_contacts"
CREATE TABLE clients_contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_client INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    lastname VARCHAR(100) NOT NULL,
    mail VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(15),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_client) REFERENCES clients(id) ON DELETE CASCADE
);
