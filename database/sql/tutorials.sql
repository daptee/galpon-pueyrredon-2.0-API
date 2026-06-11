-- =====================================================================
-- Sistema de Tutoriales / Ayuda para Clientes
-- =====================================================================

-- Módulos (temas principales)
CREATE TABLE IF NOT EXISTS `tutorial_modules` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `order`       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `status`      TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=activo, 2=inactivo',
    `created_at`  TIMESTAMP NULL DEFAULT NULL,
    `updated_at`  TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Subtemas dentro de cada módulo
CREATE TABLE IF NOT EXISTS `tutorial_subtopics` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_tutorial_module` BIGINT UNSIGNED NOT NULL,
    `name`               VARCHAR(255) NOT NULL,
    `description`        TEXT NULL,
    `order`              SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `status`             TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=activo, 2=inactivo',
    `created_at`         TIMESTAMP NULL DEFAULT NULL,
    `updated_at`         TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_subtopics_module`
        FOREIGN KEY (`id_tutorial_module`) REFERENCES `tutorial_modules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Items de tutorial.
-- Pertenecen a un subtema (id_tutorial_subtopic) O directamente a un módulo (id_tutorial_module).
-- Exactamente uno de los dos debe estar seteado.
CREATE TABLE IF NOT EXISTS `tutorial_items` (
    `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_tutorial_module`   BIGINT UNSIGNED NULL COMMENT 'Nulo si pertenece a un subtema',
    `id_tutorial_subtopic` BIGINT UNSIGNED NULL COMMENT 'Nulo si pertenece directamente al módulo',
    `title`                VARCHAR(255) NOT NULL,
    `content`              MEDIUMTEXT NULL COMMENT 'HTML enriquecido',
    `is_published`         TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=borrador, 1=publicado',
    `order`                SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`           TIMESTAMP NULL DEFAULT NULL,
    `updated_at`           TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_items_module`
        FOREIGN KEY (`id_tutorial_module`) REFERENCES `tutorial_modules` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_items_subtopic`
        FOREIGN KEY (`id_tutorial_subtopic`) REFERENCES `tutorial_subtopics` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Adjuntos de cada item (imágenes, PDFs, videos, etc.)
CREATE TABLE IF NOT EXISTS `tutorial_attachments` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_tutorial_item` BIGINT UNSIGNED NOT NULL,
    `file_path`        VARCHAR(500) NOT NULL COMMENT 'Ruta relativa al archivo',
    `original_name`    VARCHAR(255) NOT NULL COMMENT 'Nombre original del archivo',
    `file_type`        VARCHAR(50) NOT NULL DEFAULT 'other'
                       COMMENT 'image, pdf, video, other',
    `mime_type`        VARCHAR(100) NULL,
    `size`             BIGINT UNSIGNED NULL COMMENT 'Tamaño en bytes',
    `order`            SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`       TIMESTAMP NULL DEFAULT NULL,
    `updated_at`       TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_attachments_item`
        FOREIGN KEY (`id_tutorial_item`) REFERENCES `tutorial_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- Datos iniciales: Módulos (1 módulo = 1 grupo de endpoints)
-- =====================================================================

INSERT INTO `tutorial_modules` (`id`, `name`, `description`, `order`, `status`, `created_at`, `updated_at`) VALUES
( 1, 'Autenticación',             'Endpoints de login, recuperación y cambio de contraseña',                       0,  1, NOW(), NOW()),
( 2, 'Usuarios',                  'Gestión de usuarios del sistema (CRUD, habilitación y perfil propio)',           1,  1, NOW(), NOW()),
( 3, 'Tipos de Usuario',          'Administración de los tipos/roles de usuario disponibles',                      2,  1, NOW(), NOW()),
( 4, 'Clientes',                  'Gestión de clientes, tipos de cliente y clases de cliente',                     3,  1, NOW(), NOW()),
( 5, 'Lugares',                   'Lugares de entrega, tipos, áreas y tipos de recolección',                      4,  1, NOW(), NOW()),
( 6, 'Traslados y Logística',     'Transportaciones, peajes, precios de traslado por cliente/lugar y precio por hora de peón', 5, 1, NOW(), NOW()),
( 7, 'Productos',                 'Catálogo de productos, stock, estado y lista de precios PDF',                   6,  1, NOW(), NOW()),
( 8, 'Configuración de Productos','Líneas, tipos, muebles y atributos de productos',                               7,  1, NOW(), NOW()),
( 9, 'Precios de Productos',      'Consulta de precios por fecha y actualizaciones masivas de precio',             8,  1, NOW(), NOW()),
(10, 'Presupuestos',              'Ciclo completo de presupuestos: creación, estados, PDF, verificaciones y mails',9,  1, NOW(), NOW()),
(11, 'Datos de Entrega',          'Información de entrega asociada a un presupuesto',                              10, 1, NOW(), NOW()),
(12, 'Pagos',                     'Pagos, tipos, métodos y estados de pago',                                      11, 1, NOW(), NOW()),
(13, 'Eventos',                   'Eventos del sistema y sus tipos',                                               12, 1, NOW(), NOW()),
(14, 'Auditoría',                 'Auditorías generales y de presupuestos por usuario',                            13, 1, NOW(), NOW()),
(15, 'Datos Geográficos',         'Provincias y localidades',                                                      14, 1, NOW(), NOW()),
(16, 'Sistema',                   'Caché y backups',                                                               15, 1, NOW(), NOW()),
(17, 'API Pública (v1)',          'Endpoints públicos sin autenticación',                                          16, 1, NOW(), NOW()),
(18, 'Tutoriales',                'Gestión del propio sistema de tutoriales',                                      17, 1, NOW(), NOW());


-- =====================================================================
-- Datos iniciales: Subtemas (1 subtema = 1 endpoint o acción puntual)
-- =====================================================================

-- ── Módulo 1: Autenticación ──────────────────────────────────────────
INSERT INTO `tutorial_subtopics` (`id_tutorial_module`, `name`, `description`, `order`, `status`, `created_at`, `updated_at`) VALUES
(1, 'POST /auth/login',               'Autenticar usuario con email/usuario y contraseña. Devuelve JWT.',              0, 1, NOW(), NOW()),
(1, 'POST /auth/reset-password',      'Genera una contraseña aleatoria y la envía por email al usuario.',             1, 1, NOW(), NOW()),
(1, 'POST /auth/change-password',     'Cambia la contraseña del usuario autenticado verificando la actual.',          2, 1, NOW(), NOW());

-- ── Módulo 2: Usuarios ───────────────────────────────────────────────
INSERT INTO `tutorial_subtopics` (`id_tutorial_module`, `name`, `description`, `order`, `status`, `created_at`, `updated_at`) VALUES
(2, 'GET /user',                              'Listar usuarios con filtros y paginación opcional.',                       0, 1, NOW(), NOW()),
(2, 'GET /user/{id}',                         'Ver un usuario específico con todas sus relaciones.',                      1, 1, NOW(), NOW()),
(2, 'POST /user',                             'Crear un nuevo usuario.',                                                  2, 1, NOW(), NOW()),
(2, 'PUT /user/{id}',                         'Actualizar datos de un usuario existente.',                                3, 1, NOW(), NOW()),
(2, 'PUT /user/own',                          'Actualizar el perfil del propio usuario autenticado.',                     4, 1, NOW(), NOW()),
(2, 'PUT /user/disable/{id}',                 'Deshabilitar un usuario individual (cambia status a 2).',                  5, 1, NOW(), NOW()),
(2, 'PUT /user/enable/{id}',                  'Habilitar un usuario individual (cambia status a 1).',                     6, 1, NOW(), NOW()),
(2, 'PUT /user/disable-by-type/{id_user_type}','Deshabilitar todos los usuarios de un tipo determinado.',                 7, 1, NOW(), NOW()),
(2, 'PUT /user/enable-by-type/{id_user_type}', 'Habilitar todos los usuarios de un tipo determinado.',                    8, 1, NOW(), NOW());

-- ── Módulo 3: Tipos de Usuario ────────────────────────────────────────
INSERT INTO `tutorial_subtopics` (`id_tutorial_module`, `name`, `description`, `order`, `status`, `created_at`, `updated_at`) VALUES
(3, 'GET /user-type',        'Listar todos los tipos de usuario.',          0, 1, NOW(), NOW()),
(3, 'POST /user-type',       'Crear un nuevo tipo de usuario.',             1, 1, NOW(), NOW()),
(3, 'PUT /user-type/{id}',   'Actualizar un tipo de usuario existente.',    2, 1, NOW(), NOW());

-- ── Módulo 4: Clientes ────────────────────────────────────────────────
INSERT INTO `tutorial_subtopics` (`id_tutorial_module`, `name`, `description`, `order`, `status`, `created_at`, `updated_at`) VALUES
(4, 'GET /client',                  'Listar clientes con filtros, búsqueda y paginación opcional.',      0, 1, NOW(), NOW()),
(4, 'GET /client/{id}',             'Ver un cliente con sus contactos y relaciones.',                   1, 1, NOW(), NOW()),
(4, 'POST /client',                 'Crear un nuevo cliente con sus contactos.',                        2, 1, NOW(), NOW()),
(4, 'PUT /client/{id}',             'Actualizar un cliente y sus contactos.',                           3, 1, NOW(), NOW()),
(4, 'GET /client/type',             'Listar todos los tipos de cliente.',                               4, 1, NOW(), NOW()),
(4, 'POST /client/type',            'Crear un nuevo tipo de cliente.',                                  5, 1, NOW(), NOW()),
(4, 'PUT /client/type/{id}',        'Actualizar un tipo de cliente.',                                   6, 1, NOW(), NOW()),
(4, 'GET /client/classes',          'Listar todas las clases de cliente.',                              7, 1, NOW(), NOW()),
(4, 'POST /client/classes',         'Crear una nueva clase de cliente.',                                8, 1, NOW(), NOW()),
(4, 'PUT /client/classes/{id}',     'Actualizar una clase de cliente.',                                 9, 1, NOW(), NOW());

-- ── Módulo 5: Lugares ─────────────────────────────────────────────────
INSERT INTO `tutorial_subtopics` (`id_tutorial_module`, `name`, `description`, `order`, `status`, `created_at`, `updated_at`) VALUES
(5, 'GET /places',                          'Listar lugares con filtros y paginación.',            0,  1, NOW(), NOW()),
(5, 'GET /places/{id}',                     'Ver un lugar específico con sus relaciones.',         1,  1, NOW(), NOW()),
(5, 'POST /places',                         'Crear un nuevo lugar.',                               2,  1, NOW(), NOW()),
(5, 'PUT /places/{id}',                     'Actualizar un lugar existente.',                      3,  1, NOW(), NOW()),
(5, 'GET /places/export',                   'Exportar listado de lugares.',                        4,  1, NOW(), NOW()),
(5, 'GET /place-type',                      'Listar tipos de lugar.',                              5,  1, NOW(), NOW()),
(5, 'POST /place-type',                     'Crear un tipo de lugar.',                             6,  1, NOW(), NOW()),
(5, 'PUT /place-type/{id}',                 'Actualizar un tipo de lugar.',                        7,  1, NOW(), NOW()),
(5, 'GET /places-collections-types',        'Listar tipos de recolección de lugares.',             8,  1, NOW(), NOW()),
(5, 'POST /places-collections-types',       'Crear un tipo de recolección.',                       9,  1, NOW(), NOW()),
(5, 'PUT /places-collections-types/{id}',   'Actualizar un tipo de recolección.',                  10, 1, NOW(), NOW()),
(5, 'GET /places-areas',                    'Listar áreas de lugares.',                            11, 1, NOW(), NOW()),
(5, 'POST /places-areas',                   'Crear un área de lugar.',                             12, 1, NOW(), NOW()),
(5, 'PUT /places-areas/{id}',               'Actualizar un área de lugar.',                        13, 1, NOW(), NOW());

-- ── Módulo 6: Traslados y Logística ───────────────────────────────────
INSERT INTO `tutorial_subtopics` (`id_tutorial_module`, `name`, `description`, `order`, `status`, `created_at`, `updated_at`) VALUES
(6, 'GET /transportation',                       'Listar transportaciones disponibles.',                                          0,  1, NOW(), NOW()),
(6, 'GET /transportation/{id}',                  'Ver una transportación con su detalle completo.',                               1,  1, NOW(), NOW()),
(6, 'POST /transportation',                      'Crear una nueva transportación.',                                               2,  1, NOW(), NOW()),
(6, 'PUT /transportation/{id}',                  'Actualizar una transportación existente.',                                      3,  1, NOW(), NOW()),
(6, 'GET /tolls',                                'Listar todos los peajes.',                                                      4,  1, NOW(), NOW()),
(6, 'POST /tolls',                               'Crear un nuevo peaje.',                                                         5,  1, NOW(), NOW()),
(6, 'PUT /tolls/{id}',                           'Actualizar un peaje.',                                                          6,  1, NOW(), NOW()),
(6, 'GET /client-place-transport-prices',        'Listar precios fijos de traslado por cliente y lugar.',                        7,  1, NOW(), NOW()),
(6, 'GET /client-place-transport-prices/{id}',   'Ver un precio de traslado específico.',                                        8,  1, NOW(), NOW()),
(6, 'GET /client-place-transport-prices/check',  'Verificar si existe un precio de traslado para un cliente+lugar.',             9,  1, NOW(), NOW()),
(6, 'POST /client-place-transport-prices',       'Crear un precio fijo de traslado para un cliente y lugar.',                    10, 1, NOW(), NOW()),
(6, 'PUT /client-place-transport-prices/{id}',   'Actualizar un precio de traslado existente.',                                  11, 1, NOW(), NOW()),
(6, 'DELETE /client-place-transport-prices/{id}','Eliminar un precio de traslado.',                                              12, 1, NOW(), NOW()),
(6, 'GET /pawn-hour-price',                      'Listar precios por hora de peón.',                                             13, 1, NOW(), NOW()),
(6, 'POST /pawn-hour-price',                     'Crear un precio por hora de peón.',                                            14, 1, NOW(), NOW()),
(6, 'PUT /pawn-hour-price/{id}',                 'Actualizar un precio por hora de peón.',                                       15, 1, NOW(), NOW());

-- ── Módulo 7: Productos ───────────────────────────────────────────────
INSERT INTO `tutorial_subtopics` (`id_tutorial_module`, `name`, `description`, `order`, `status`, `created_at`, `updated_at`) VALUES
(7, 'GET /products',                  'Listar productos con filtros de estado, tipo, línea, búsqueda y paginación.',   0, 1, NOW(), NOW()),
(7, 'GET /products/{id}',             'Ver un producto con imágenes, precios, atributos y combos.',                    1, 1, NOW(), NOW()),
(7, 'POST /products',                 'Crear un producto con imágenes, precios, atributos y combos.',                  2, 1, NOW(), NOW()),
(7, 'POST /products/{id}',            'Actualizar un producto existente.',                                             3, 1, NOW(), NOW()),
(7, 'PUT /products/status/{id}',      'Cambiar el estado de un producto.',                                             4, 1, NOW(), NOW()),
(7, 'GET /products/catalog',          'Catálogo de productos con disponibilidad de stock.',                            5, 1, NOW(), NOW()),
(7, 'GET /products/stock/report',     'Reporte de uso de stock de los últimos 7 días.',                                6, 1, NOW(), NOW()),
(7, 'GET /products/stock/calendar',   'Calendario mensual de stock por presupuesto.',                                  7, 1, NOW(), NOW()),
(7, 'GET /products/stock/export',     'Exportar reporte de stock en Excel.',                                           8, 1, NOW(), NOW()),
(7, 'GET /products/price-list/pdf',   'Generar lista de precios vigentes en PDF.',                                     9, 1, NOW(), NOW());

-- ── Módulo 8: Configuración de Productos ─────────────────────────────
INSERT INTO `tutorial_subtopics` (`id_tutorial_module`, `name`, `description`, `order`, `status`, `created_at`, `updated_at`) VALUES
(8, 'GET /product-line',             'Listar líneas de producto.',             0,  1, NOW(), NOW()),
(8, 'POST /product-line',            'Crear una línea de producto.',           1,  1, NOW(), NOW()),
(8, 'PUT /product-line/{id}',        'Actualizar una línea de producto.',      2,  1, NOW(), NOW()),
(8, 'DELETE /product-line/{id}',     'Eliminar una línea de producto.',        3,  1, NOW(), NOW()),
(8, 'GET /product-type',             'Listar tipos de producto.',              4,  1, NOW(), NOW()),
(8, 'POST /product-type',            'Crear un tipo de producto.',             5,  1, NOW(), NOW()),
(8, 'PUT /product-type/{id}',        'Actualizar un tipo de producto.',        6,  1, NOW(), NOW()),
(8, 'DELETE /product-type/{id}',     'Eliminar un tipo de producto.',          7,  1, NOW(), NOW()),
(8, 'GET /product-furniture',        'Listar muebles de producto.',            8,  1, NOW(), NOW()),
(8, 'POST /product-furniture',       'Crear un mueble de producto.',           9,  1, NOW(), NOW()),
(8, 'PUT /product-furniture/{id}',   'Actualizar un mueble de producto.',      10, 1, NOW(), NOW()),
(8, 'DELETE /product-furniture/{id}','Eliminar un mueble de producto.',        11, 1, NOW(), NOW()),
(8, 'GET /product-attribute',        'Listar atributos de producto.',          12, 1, NOW(), NOW()),
(8, 'POST /product-attribute',       'Crear un atributo de producto.',         13, 1, NOW(), NOW()),
(8, 'PUT /product-attribute/{id}',   'Actualizar un atributo de producto.',    14, 1, NOW(), NOW()),
(8, 'DELETE /product-attribute/{id}','Eliminar un atributo de producto.',      15, 1, NOW(), NOW());

-- ── Módulo 9: Precios de Productos ───────────────────────────────────
INSERT INTO `tutorial_subtopics` (`id_tutorial_module`, `name`, `description`, `order`, `status`, `created_at`, `updated_at`) VALUES
(9, 'GET /product-prices/by-date',                  'Consultar precios de productos vigentes en una fecha determinada.',        0, 1, NOW(), NOW()),
(9, 'GET /product-prices/export-prices-by-date',    'Exportar en Excel los precios vigentes en una fecha.',                    1, 1, NOW(), NOW()),
(9, 'GET /bulk-price-updates',                      'Listar actualizaciones masivas de precio registradas.',                   2, 1, NOW(), NOW()),
(9, 'POST /bulk-price-updates',                     'Crear una actualización masiva de precios.',                              3, 1, NOW(), NOW()),
(9, 'PUT /bulk-price-updates/{id}',                 'Actualizar una actualización masiva de precios.',                         4, 1, NOW(), NOW()),
(9, 'DELETE /bulk-price-updates/{id}',              'Eliminar una actualización masiva de precios.',                           5, 1, NOW(), NOW());

-- ── Módulo 10: Presupuestos ───────────────────────────────────────────
INSERT INTO `tutorial_subtopics` (`id_tutorial_module`, `name`, `description`, `order`, `status`, `created_at`, `updated_at`) VALUES
(10, 'GET /budgets',                                          'Listar presupuestos con filtros y paginación.',                                 0,  1, NOW(), NOW()),
(10, 'GET /budgets/{id}',                                     'Ver un presupuesto completo con productos, entrega y pagos.',                   1,  1, NOW(), NOW()),
(10, 'POST /budgets',                                         'Crear un nuevo presupuesto.',                                                   2,  1, NOW(), NOW()),
(10, 'PUT /budgets/{id}',                                     'Actualizar un presupuesto existente.',                                          3,  1, NOW(), NOW()),
(10, 'GET /budgets/pdf/{id}',                                 'Generar y descargar el PDF del presupuesto.',                                   4,  1, NOW(), NOW()),
(10, 'GET /budgets/tree-status/{id}',                         'Obtener el árbol de estados del presupuesto y sus hijos.',                      5,  1, NOW(), NOW()),
(10, 'PUT /budgets/status/{id}',                              'Cambiar el estado de un presupuesto.',                                          6,  1, NOW(), NOW()),
(10, 'PUT /budgets/observations/{id}',                        'Actualizar las observaciones de un presupuesto.',                               7,  1, NOW(), NOW()),
(10, 'PUT /budgets/contact/{id}',                             'Actualizar el contacto asociado al presupuesto.',                               8,  1, NOW(), NOW()),
(10, 'POST /budgets/resend/{id}',                             'Reenviar el email de notificación del presupuesto.',                            9,  1, NOW(), NOW()),
(10, 'POST /budgets/sendMails/{id}',                          'Enviar mails de notificación a los destinatarios del presupuesto.',             10, 1, NOW(), NOW()),
(10, 'POST /budgets/check-stock',                             'Verificar disponibilidad de stock para los productos de un presupuesto.',       11, 1, NOW(), NOW()),
(10, 'POST /budgets/check-stock-bulk',                        'Verificar stock de forma masiva para múltiples presupuestos.',                  12, 1, NOW(), NOW()),
(10, 'POST /budgets/check-stock-bulk-without-budget',         'Verificar stock masivo sin estar asociado a un presupuesto.',                   13, 1, NOW(), NOW()),
(10, 'POST /budgets/check-price',                             'Verificar precios vigentes para los productos de un presupuesto.',              14, 1, NOW(), NOW()),
(10, 'POST /budgets/check-price-bulk',                        'Verificar precios de forma masiva para múltiples presupuestos.',                15, 1, NOW(), NOW()),
(10, 'POST /budgets/check-price-bulk-without-budget',         'Verificar precios masivo sin estar asociado a un presupuesto.',                 16, 1, NOW(), NOW()),
(10, 'POST /budgets/check-budget',                            'Verificación completa de stock y precios del presupuesto.',                     17, 1, NOW(), NOW()),
(10, 'GET /budgets/generate-pdf-delivery-information/{id}',   'Generar PDF con la información de entrega del presupuesto.',                    18, 1, NOW(), NOW()),
(10, 'POST /budgets/calculate-volume',                        'Calcular el volumen total de los productos en un presupuesto.',                 19, 1, NOW(), NOW()),
(10, 'GET /budget-status',                                    'Listar todos los estados de presupuesto disponibles.',                          20, 1, NOW(), NOW());

-- ── Módulo 11: Datos de Entrega ────────────────────────────────────────
INSERT INTO `tutorial_subtopics` (`id_tutorial_module`, `name`, `description`, `order`, `status`, `created_at`, `updated_at`) VALUES
(11, 'POST /budget-delivery-data',       'Crear los datos de entrega asociados a un presupuesto.',     0, 1, NOW(), NOW()),
(11, 'PUT /budget-delivery-data/{id}',   'Actualizar los datos de entrega de un presupuesto.',         1, 1, NOW(), NOW());

-- ── Módulo 12: Pagos ───────────────────────────────────────────────────
INSERT INTO `tutorial_subtopics` (`id_tutorial_module`, `name`, `description`, `order`, `status`, `created_at`, `updated_at`) VALUES
(12, 'GET /payment',                     'Listar pagos con filtros.',                               0,  1, NOW(), NOW()),
(12, 'POST /payment',                    'Registrar un nuevo pago.',                                1,  1, NOW(), NOW()),
(12, 'PUT /payment/update-status/{id}',  'Actualizar el estado de un pago.',                       2,  1, NOW(), NOW()),
(12, 'GET /payment-type',                'Listar tipos de pago.',                                  3,  1, NOW(), NOW()),
(12, 'POST /payment-type',               'Crear un tipo de pago.',                                 4,  1, NOW(), NOW()),
(12, 'PUT /payment-type/{id}',           'Actualizar un tipo de pago.',                            5,  1, NOW(), NOW()),
(12, 'GET /payment-method',              'Listar métodos de pago disponibles.',                    6,  1, NOW(), NOW()),
(12, 'POST /payment-method',             'Crear un método de pago.',                               7,  1, NOW(), NOW()),
(12, 'PUT /payment-method/{id}',         'Actualizar un método de pago.',                          8,  1, NOW(), NOW()),
(12, 'GET /payment-status',              'Listar estados de pago disponibles.',                    9,  1, NOW(), NOW()),
(12, 'POST /payment-status',             'Crear un estado de pago.',                               10, 1, NOW(), NOW()),
(12, 'PUT /payment-status/{id}',         'Actualizar un estado de pago.',                          11, 1, NOW(), NOW());

-- ── Módulo 13: Eventos ─────────────────────────────────────────────────
INSERT INTO `tutorial_subtopics` (`id_tutorial_module`, `name`, `description`, `order`, `status`, `created_at`, `updated_at`) VALUES
(13, 'GET /event',                         'Listar eventos con filtros.',                          0, 1, NOW(), NOW()),
(13, 'GET /event/{id}',                    'Ver un evento específico.',                            1, 1, NOW(), NOW()),
(13, 'GET /event/export-events-by-date',   'Exportar eventos filtrados por rango de fechas.',      2, 1, NOW(), NOW()),
(13, 'GET /event-type',                    'Listar tipos de evento disponibles.',                  3, 1, NOW(), NOW()),
(13, 'POST /event-type',                   'Crear un tipo de evento.',                             4, 1, NOW(), NOW()),
(13, 'PUT /event-type/{id}',               'Actualizar un tipo de evento.',                        5, 1, NOW(), NOW());

-- ── Módulo 14: Auditoría ───────────────────────────────────────────────
INSERT INTO `tutorial_subtopics` (`id_tutorial_module`, `name`, `description`, `order`, `status`, `created_at`, `updated_at`) VALUES
(14, 'GET /audith',              'Listar todas las entradas de auditoría del sistema.',             0, 1, NOW(), NOW()),
(14, 'GET /audith/user/{id}',    'Ver las auditorías generadas por un usuario específico.',         1, 1, NOW(), NOW()),
(14, 'GET /budgets-audith/{id}', 'Ver el historial de cambios de un presupuesto específico.',      2, 1, NOW(), NOW());

-- ── Módulo 15: Datos Geográficos ───────────────────────────────────────
INSERT INTO `tutorial_subtopics` (`id_tutorial_module`, `name`, `description`, `order`, `status`, `created_at`, `updated_at`) VALUES
(15, 'GET /province',        'Listar todas las provincias.',                           0, 1, NOW(), NOW()),
(15, 'GET /province/{id}',   'Ver una provincia específica.',                          1, 1, NOW(), NOW()),
(15, 'GET /locality/{id}',   'Listar las localidades de una provincia por su ID.',     2, 1, NOW(), NOW());

-- ── Módulo 16: Sistema ─────────────────────────────────────────────────
INSERT INTO `tutorial_subtopics` (`id_tutorial_module`, `name`, `description`, `order`, `status`, `created_at`, `updated_at`) VALUES
(16, 'GET /clear-cache',     'Vaciar la caché de la aplicación.',                      0, 1, NOW(), NOW()),
(16, 'GET /backup',          'Generar un backup de la base de datos.',                 1, 1, NOW(), NOW()),
(16, 'GET /backup/clean',    'Eliminar los backups más antiguos según configuración.', 2, 1, NOW(), NOW());

-- ── Módulo 17: API Pública (v1) ────────────────────────────────────────
INSERT INTO `tutorial_subtopics` (`id_tutorial_module`, `name`, `description`, `order`, `status`, `created_at`, `updated_at`) VALUES
(17, 'GET /v1/products',          'Listar productos del catálogo público (sin autenticación).',        0, 1, NOW(), NOW()),
(17, 'GET /v1/product-line',      'Listar líneas de producto públicas (sin autenticación).',           1, 1, NOW(), NOW()),
(17, 'GET /v1/product-furniture', 'Listar muebles de producto públicos (sin autenticación).',          2, 1, NOW(), NOW()),
(17, 'POST /v1/contact-form',     'Enviar formulario de contacto (sin autenticación).',                3, 1, NOW(), NOW());

-- ── Módulo 18: Tutoriales ──────────────────────────────────────────────
INSERT INTO `tutorial_subtopics` (`id_tutorial_module`, `name`, `description`, `order`, `status`, `created_at`, `updated_at`) VALUES
(18, 'GET /tutorials/tree',                            'Obtener el árbol completo de navegación de tutoriales.',              0,  1, NOW(), NOW()),
(18, 'GET /tutorials',                                 'Listar items de tutorial con filtros y paginación.',                  1,  1, NOW(), NOW()),
(18, 'GET /tutorials/{id}',                            'Ver un tutorial completo con HTML y adjuntos.',                       2,  1, NOW(), NOW()),
(18, 'POST /tutorials',                                'Crear un tutorial con adjuntos (multipart/form-data).',               3,  1, NOW(), NOW()),
(18, 'POST /tutorials/{id} (update)',                  'Actualizar un tutorial existente.',                                   4,  1, NOW(), NOW()),
(18, 'DELETE /tutorials/{id}',                         'Eliminar un tutorial y sus archivos físicos.',                        5,  1, NOW(), NOW()),
(18, 'POST /tutorials/{id}/attachments',               'Subir adjuntos adicionales a un tutorial existente.',                 6,  1, NOW(), NOW()),
(18, 'DELETE /tutorials/{id}/attachments/{aid}',       'Eliminar un adjunto individual de un tutorial.',                      7,  1, NOW(), NOW()),
(18, 'GET /tutorial-modules',                          'Listar módulos de tutorial con conteo de subtemas.',                  8,  1, NOW(), NOW()),
(18, 'GET /tutorial-modules/{id}',                     'Ver un módulo con sus subtemas.',                                     9,  1, NOW(), NOW()),
(18, 'POST /tutorial-modules',                         'Crear un módulo de tutorial.',                                        10, 1, NOW(), NOW()),
(18, 'PUT /tutorial-modules/{id}',                     'Actualizar un módulo de tutorial.',                                   11, 1, NOW(), NOW()),
(18, 'DELETE /tutorial-modules/{id}',                  'Eliminar un módulo y todo su contenido en cascada.',                  12, 1, NOW(), NOW()),
(18, 'GET /tutorial-subtopics',                        'Listar subtemas con filtros.',                                        13, 1, NOW(), NOW()),
(18, 'GET /tutorial-subtopics/{id}',                   'Ver un subtema con su módulo e items.',                               14, 1, NOW(), NOW()),
(18, 'POST /tutorial-subtopics',                       'Crear un subtema dentro de un módulo.',                               15, 1, NOW(), NOW()),
(18, 'PUT /tutorial-subtopics/{id}',                   'Actualizar un subtema existente.',                                    16, 1, NOW(), NOW()),
(18, 'DELETE /tutorial-subtopics/{id}',                'Eliminar un subtema y su contenido en cascada.',                      17, 1, NOW(), NOW());
