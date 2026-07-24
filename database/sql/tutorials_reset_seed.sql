-- =====================================================================
-- Reset de módulos y subtemas de tutoriales
-- Borra todo el seed anterior y carga solo los 4 módulos necesarios
-- =====================================================================

-- Limpiar en orden (FK: attachments > items > subtopics > modules)
DELETE FROM `tutorial_attachments`;
DELETE FROM `tutorial_items`;
DELETE FROM `tutorial_subtopics`;
DELETE FROM `tutorial_modules`;

-- Resetear auto-increment
ALTER TABLE `tutorial_attachments` AUTO_INCREMENT = 1;
ALTER TABLE `tutorial_items`       AUTO_INCREMENT = 1;
ALTER TABLE `tutorial_subtopics`   AUTO_INCREMENT = 1;
ALTER TABLE `tutorial_modules`     AUTO_INCREMENT = 1;

-- =====================================================================
-- Módulos
-- =====================================================================

INSERT INTO `tutorial_modules` (`id`, `name`, `description`, `order`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Productos',          'Guías del módulo de productos',          0, 1, NOW(), NOW()),
(2, 'Reporte de Precios', 'Guías del módulo de reportes de precios', 1, 1, NOW(), NOW()),
(3, 'Presupuestos',       'Guías del módulo de presupuestos',       2, 1, NOW(), NOW()),
(4, 'Eventos',            'Guías del módulo de eventos',            3, 1, NOW(), NOW());

-- =====================================================================
-- Subtemas
-- =====================================================================

-- Dentro de Productos (id=1)
INSERT INTO `tutorial_subtopics` (`id_tutorial_module`, `name`, `description`, `order`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Armar presupuesto', 'Cómo seleccionar productos y crear un presupuesto. POST /budgets', 0, 1, NOW(), NOW());

-- Dentro de Presupuestos (id=3)
INSERT INTO `tutorial_subtopics` (`id_tutorial_module`, `name`, `description`, `order`, `status`, `created_at`, `updated_at`) VALUES
(3, 'Actualizar versión de presupuesto', 'Cómo editar y actualizar un presupuesto existente. PUT /budgets/:id', 0, 1, NOW(), NOW());
