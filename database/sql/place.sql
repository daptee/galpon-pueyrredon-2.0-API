SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS places_types, places_collections_types, places, tolls, places_tolls, places_area;

SET FOREIGN_KEY_CHECKS = 1;

-- Tabla places_types
CREATE TABLE places_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    status INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insertar datos en places_types
INSERT INTO places_types (name, status) VALUES
('Localidad', 1),
('Evento', 1),
('Barrio / Club', 1);

-- Tabla places_collections_types
CREATE TABLE places_collections_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    status INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insertar datos en places_collections_types
INSERT INTO places_collections_types (name, status) VALUES
('Por hora', 1),
('Por distancia', 1);

-- Tabla places
CREATE TABLE places (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_place_type INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    id_province INT NOT NULL,
    id_locality INT NOT NULL,
    id_place_collection_type INT NOT NULL,
    id_place_area INT NOT NULL,
    distance DECIMAL(10,2),
    travel_time TIME,
    address VARCHAR(255),
    phone VARCHAR(20),
    complexity_factor DECIMAL(5,2),
    observations TEXT,
    status INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_place_type) REFERENCES places_types(id),
    FOREIGN KEY (id_province) REFERENCES provinces(id),
    FOREIGN KEY (id_locality) REFERENCES localities(id),
    FOREIGN KEY (id_place_collection_type) REFERENCES places_collections_types(id),
    FOREIGN KEY (id_place_area) REFERENCES places_area(id)
);

-- Tabla tolls
CREATE TABLE tolls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    cost DECIMAL(10,2) NOT NULL,
    status INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla places_tolls
CREATE TABLE places_tolls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_place INT NOT NULL,
    id_toll INT NOT NULL,
    status INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_place) REFERENCES places(id),
    FOREIGN KEY (id_toll) REFERENCES tolls(id)
);

-- Tabla places_area
CREATE TABLE places_area (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    status INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

ALTER TABLE places
MODIFY COLUMN travel_time VARCHAR(20);