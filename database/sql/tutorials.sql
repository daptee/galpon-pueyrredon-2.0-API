-- =====================================================================
-- Sistema de Tutoriales / Ayuda para Clientes
-- =====================================================================

-- Módulos (temas principales, ej: "Presupuestos", "Productos", "Pagos")
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

-- Subtemas dentro de cada módulo (ej: "Crear un presupuesto", "Aprobar presupuesto")
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

-- Items de tutorial (el contenido real: HTML enriquecido + metadatos)
CREATE TABLE IF NOT EXISTS `tutorial_items` (
    `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_tutorial_subtopic` BIGINT UNSIGNED NOT NULL,
    `title`                VARCHAR(255) NOT NULL,
    `content`              MEDIUMTEXT NULL COMMENT 'HTML enriquecido',
    `content_type`         TINYINT UNSIGNED NOT NULL DEFAULT 1
                           COMMENT '1=tutorial, 2=documento, 3=guia, 4=faq, 5=video',
    `cover_image`          VARCHAR(500) NULL COMMENT 'Ruta de imagen de portada',
    `is_published`         TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=borrador, 1=publicado',
    `order`                SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`           TIMESTAMP NULL DEFAULT NULL,
    `updated_at`           TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
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
