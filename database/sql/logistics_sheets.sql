CREATE TABLE logistics_sheets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_budget INT NOT NULL UNIQUE,
    token CHAR(36) NOT NULL UNIQUE,

    -- Datos básicos del evento
    budget_ratified TINYINT(1) NOT NULL DEFAULT 0,
    id_event_type INT NULL,
    event_type_other VARCHAR(255) NULL,
    event_start_datetime DATETIME NULL,
    event_end_datetime DATETIME NULL,
    address VARCHAR(255) NULL,
    address_maps_link VARCHAR(500) NULL,
    accessibility_comments VARCHAR(500) NULL,
    order_contact_name VARCHAR(255) NULL,
    order_contact_phone VARCHAR(20) NULL,

    -- Armado y desarme
    delivery_windows JSON NULL,
    pickup_windows JSON NULL,
    reception_contact_name VARCHAR(255) NULL,
    reception_contact_phone VARCHAR(20) NULL,
    cushion_color VARCHAR(100) NULL,
    additional_order_details VARCHAR(500) NULL,

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

    FOREIGN KEY (id_budget) REFERENCES budgets(id),
    FOREIGN KEY (id_event_type) REFERENCES event_types(id)
);
