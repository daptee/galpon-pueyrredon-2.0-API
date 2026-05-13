-- Tabla cabecera: combinación cliente + lugar con precio fijo de traslado
CREATE TABLE client_place_transport_prices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_client INT NOT NULL,
    id_place INT NOT NULL,
    observations TEXT NULL,
    status TINYINT DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_client) REFERENCES clients(id),
    FOREIGN KEY (id_place) REFERENCES places(id),
    UNIQUE KEY unique_client_place (id_client, id_place)
);

-- Tabla ítems: rangos de volumen máximo y precio asociados a cada cabecera
-- max_volume representa el tope del rango: se aplica el precio del primer rango
-- cuyo max_volume sea MAYOR al volumen del presupuesto.
-- Ejemplo: max_volume=20 → aplica si volumen < 20; max_volume=30 → aplica si volumen < 30
CREATE TABLE client_place_transport_price_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_client_place_transport_price INT NOT NULL,
    max_volume DECIMAL(10,2) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_client_place_transport_price)
        REFERENCES client_place_transport_prices(id) ON DELETE CASCADE
);

-- Columnas en budgets para referenciar el precio fijo aplicado al crear el presupuesto
-- Si ambos son NULL, el traslado fue calculado de forma estándar
-- Si tienen valor, el traslado fue tomado de la lista de precios fijos
ALTER TABLE budgets
    ADD COLUMN id_client_place_transport_price INT NULL,
    ADD COLUMN id_client_place_transport_price_item INT NULL,
    ADD CONSTRAINT fk_budget_cptp FOREIGN KEY (id_client_place_transport_price)
        REFERENCES client_place_transport_prices(id),
    ADD CONSTRAINT fk_budget_cptpi FOREIGN KEY (id_client_place_transport_price_item)
        REFERENCES client_place_transport_price_items(id);
