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
    -- Documentos de seguro adicionales (ART, seguro de vehículos, etc.), más
    -- allá del principal (insurance_document_path). Array JSON de objetos
    -- {path, original_name}, se van agregando de a uno, nunca se pisan entre sí.
    insurance_additional_documents JSON NULL,
    -- Alternativa en texto al documento de seguro, para cuando el cliente
    -- todavía no tiene el archivo pero puede describir la cobertura/solicitud.
    -- Satisface el requisito de "Documento o texto de solicitud de seguros"
    -- junto con insurance_document_path (alcanza con uno de los dos).
    insurance_request_text VARCHAR(1000) NULL,
    additional_requirements VARCHAR(500) NULL,

    -- Plano de armado
    assembly_plan_path VARCHAR(255) NULL,

    -- Control de completitud (carga incremental)
    -- completion_percentage lo manda el frontend (0-100): es el % de avance
    -- que calcula la pantalla del formulario, distinto de is_completed (que
    -- lo calcula el backend en base a los campos mandatorios). Puramente
    -- informativo, no afecta is_completed/completed_at.
    field_status JSON NULL,
    completion_percentage TINYINT UNSIGNED NULL,
    is_completed TINYINT(1) NOT NULL DEFAULT 0,
    completed_at DATETIME NULL,

    -- Recordatorios automáticos por mail (cron). Array JSON de enteros con
    -- los "días antes del evento" ya notificados (ej. [7,3]), para no
    -- volver a mandar el mismo recordatorio si el cron corre más de una vez
    -- el mismo día.
    reminder_days_sent JSON NULL,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (id_budget) REFERENCES budgets(id)
);
