-- Verificación por código enviado al email (login y acciones sensibles).
-- Por defecto queda activa para todos los usuarios; cada uno puede desactivarla
-- desde Configuración > Seguridad.

ALTER TABLE users ADD COLUMN two_factor_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER status;

CREATE TABLE verification_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    purpose VARCHAR(30) NOT NULL,
    code_hash CHAR(64) NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_verification_codes_user_purpose (id_user, purpose),
    CONSTRAINT fk_verification_codes_user FOREIGN KEY (id_user) REFERENCES users(id) ON DELETE CASCADE
);
