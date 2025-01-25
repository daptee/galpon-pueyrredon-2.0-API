SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS users, user_types, themes, clients, status;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE user_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    permissions JSON NOT NULL,
    status INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO user_types (name, permissions, status)
VALUES
    ('Admin', '{}', 1),
    ('Editor', '{}', 1),
    ('Viewer', '{}', 1),
    ('Guest', '{}', 2);


CREATE TABLE themes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL
);

INSERT INTO themes (name) VALUES ('Default'), ('Galpon Pueyrredon');

CREATE TABLE clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL -- Campos adicionales según sea necesario.
);

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_user_type INT NOT NULL,
    user VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    lastname VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(20),
    is_internal BOOLEAN NOT NULL DEFAULT FALSE,
    id_client INT,
    permissions JSON NOT NULL,
    theme INT NOT NULL,
    status INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_user_type) REFERENCES user_types(id) ON DELETE CASCADE,
    FOREIGN KEY (id_client) REFERENCES clients(id) ON DELETE SET NULL,
    FOREIGN KEY (theme) REFERENCES themes(id) ON DELETE RESTRICT
);

CREATE TABLE status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO status (name) VALUES ('activo'), ('inactivo'), ('pendiente');