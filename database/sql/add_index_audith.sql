-- Índice compuesto para optimizar consultas de auditoría por usuario y fecha
-- Resuelve el error "Out of sort memory" al filtrar/ordenar por id_user + created_at

ALTER TABLE audith
    ADD INDEX audith_id_user_created_at_index (id_user, created_at);
