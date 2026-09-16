CREATE TABLE logistics_sheets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_budget INT NOT NULL UNIQUE,
    token CHAR(36) NOT NULL UNIQUE,

    -- Datos básicos del evento
    -- La fecha/hora de inicio y la dirección NO se guardan acá: se toman en
    -- vivo de budgets.date_event/time_event y de la dirección del place del
    -- presupuesto, para que si se edita el evento la ficha quede al día.
    -- El tipo de evento (cuando es del combo) tampoco: es el mismo dato que
    -- budget_delivery_data.id_event_type, se lee/escribe directo ahí.
    budget_ratified TINYINT(1) NOT NULL DEFAULT 0,
    event_type_other VARCHAR(255) NULL,
    event_end_datetime DATETIME NULL,
    address_maps_link VARCHAR(500) NULL,
    accessibility_comments VARCHAR(500) NULL,

    -- Armado y desarme
    -- Los contactos y additional_order_details tampoco se guardan acá: son
    -- los mismos campos que budget_delivery_data (coordination_contact/
    -- cellphone_coordination, reception_contact/cellphone_reception,
    -- additional_order_details), se leen/escriben directo ahí.
    delivery_windows JSON NULL,
    pickup_windows JSON NULL,
    cushion_color VARCHAR(100) NULL,

    -- Requerimientos
    insurance_required ENUM('yes', 'not_applicable', 'later') NULL,
    insurance_document_path VARCHAR(255) NULL,
    additional_requirements VARCHAR(500) NULL,

    -- Plano de armado
    assembly_plan_path VARCHAR(255) NULL,

    -- Control de completitud (carga incremental)
    field_status JSON NULL,
    is_completed TINYINT(1) NOT NULL DEFAULT 0,
    completed_at DATETIME NULL,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (id_budget) REFERENCES budgets(id)
);
