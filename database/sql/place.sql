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

-- Actualizar id_place_area en la tabla places según la distancia

-- CABA (id_place_area = 1) - Distancia 0-25km aproximadamente
UPDATE places 
SET id_place_area = 1 
WHERE distance <= 25 AND id_place_area IS NULL;

-- AMBA - Norte (id_place_area = 2) - Distancia 25-50km en zonas norte
UPDATE places 
SET id_place_area = 2 
WHERE distance > 25 AND distance <= 50 
  AND (id_province = 2 OR id_locality IN (239, 255, 249, 221, 242))
  AND id_place_area IS NULL;

-- GBA Norte - 25K-50K (id_place_area = 3) - Distancia 25-50km
UPDATE places 
SET id_place_area = 3 
WHERE distance > 25 AND distance <= 50 
  AND id_place_area IS NULL;

-- AMBA - Oeste (id_place_area = 10) - Zonas oeste cercanas
UPDATE places 
SET id_place_area = 10 
WHERE distance > 25 AND distance <= 50 
  AND (id_locality IN (192, 195, 219, 164, 176, 218, 241))
  AND id_place_area IS NULL;

-- GBA - Oeste - 25K-50K (id_place_area = 8)
UPDATE places 
SET id_place_area = 8 
WHERE distance > 25 AND distance <= 50 
  AND id_place_area IS NULL;

-- GBA Norte - 50k-100K (id_place_area = 4) - Distancia 50-100km
UPDATE places 
SET id_place_area = 4 
WHERE distance > 50 AND distance <= 100 
  AND (id_province = 2 OR id_province = 1)
  AND id_place_area IS NULL;

-- GBA Oeste - 50K-100K (id_place_area = 9)
UPDATE places 
SET id_place_area = 9 
WHERE distance > 50 AND distance <= 100 
  AND id_place_area IS NULL;

-- AMBA - Sur (id_place_area = 11) - Zonas sur
UPDATE places 
SET id_place_area = 11 
WHERE (id_locality IN (150, 156, 161, 179, 181, 205, 229))
  AND id_place_area IS NULL;

-- Extraradio 100-400K (id_place_area = 5) - Distancia 100-400km
UPDATE places 
SET id_place_area = 5 
WHERE distance > 100 AND distance <= 400 
  AND id_place_area IS NULL;

-- Extraradio - +400K (id_place_area = 6) - Distancia mayor a 400km
UPDATE places 
SET id_place_area = 6 
WHERE distance > 400 
  AND id_place_area IS NULL;

-- SIN TRASLADOS (id_place_area = 7) - Para casos especiales donde distance = 0 o NULL
-- Este debe ser asignado manualmente según cada caso
UPDATE places 
SET id_place_area = 7 
WHERE (distance = 0 OR distance IS NULL OR complexity_factor >= 100)
  AND id_place_area IS NULL;