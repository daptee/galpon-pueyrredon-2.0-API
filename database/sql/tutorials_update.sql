-- =====================================================================
-- Actualización del sistema de tutoriales
-- Ejecutar solo si ya se corrió el SQL original (tutorials.sql)
-- =====================================================================

-- 1. Eliminar content_type si existe
SET @col1 = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'tutorial_items'
      AND COLUMN_NAME  = 'content_type'
);
SET @sql1 = IF(@col1 > 0,
    'ALTER TABLE `tutorial_items` DROP COLUMN `content_type`',
    'SELECT 1'
);
PREPARE stmt FROM @sql1; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. Eliminar cover_image si existe
SET @col2 = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'tutorial_items'
      AND COLUMN_NAME  = 'cover_image'
);
SET @sql2 = IF(@col2 > 0,
    'ALTER TABLE `tutorial_items` DROP COLUMN `cover_image`',
    'SELECT 1'
);
PREPARE stmt FROM @sql2; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3. Agregar id_tutorial_module si no existe
SET @col3 = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'tutorial_items'
      AND COLUMN_NAME  = 'id_tutorial_module'
);
SET @sql3 = IF(@col3 = 0,
    'ALTER TABLE `tutorial_items` ADD COLUMN `id_tutorial_module` BIGINT UNSIGNED NULL COMMENT ''Nulo si pertenece a un subtema'' AFTER `id`',
    'SELECT 1'
);
PREPARE stmt FROM @sql3; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4. Agregar FK fk_items_module si no existe
SET @fk = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME         = 'tutorial_items'
      AND CONSTRAINT_NAME    = 'fk_items_module'
      AND CONSTRAINT_TYPE    = 'FOREIGN KEY'
);
SET @sql4 = IF(@fk = 0,
    'ALTER TABLE `tutorial_items` ADD CONSTRAINT `fk_items_module` FOREIGN KEY (`id_tutorial_module`) REFERENCES `tutorial_modules` (`id`) ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql4; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5. Asegurar que id_tutorial_subtopic sea nullable
ALTER TABLE `tutorial_items`
    MODIFY COLUMN `id_tutorial_subtopic` BIGINT UNSIGNED NULL
        COMMENT 'Nulo si pertenece directamente al módulo';
