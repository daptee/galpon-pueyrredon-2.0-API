<?php
/**
 * Script de migración de contraseñas
 *
 * Este script migra las contraseñas del sistema anterior (AES-256-CBC)
 * al nuevo sistema usando bcrypt (Laravel password_hash)
 *
 * USO: php migrate_passwords.php
 */

// Configuración de base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'gpueyrre_don_bd_prod');
define('DB_USER', 'root');
define('DB_PASS', '');

// Clase Encrypter del sistema anterior
class OldEncrypter
{
    private static function encrypt_decrypt($action, $string) {
        $output = false;
        $encrypt_method = "AES-256-CBC";
        $secret_key = 'galpo-pueyrredon_secretKey!';
        $secret_iv = 'galpo-pueyrredon_secretIV!';

        // hash
        $key = hash('sha256', $secret_key);

        // iv - encrypt method AES-256-CBC expects 16 bytes
        $iv = substr(hash('sha256', $secret_iv), 0, 16);

        if ($action == 'encrypt') {
            $output = base64_encode(openssl_encrypt($string, $encrypt_method, $key, 0, $iv));
        } else if ($action == 'decrypt') {
            $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);
        }

        return $output;
    }

    public static function decrypt($string)
    {
        return self::encrypt_decrypt('decrypt', $string);
    }
}

// Conectar a la base de datos
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    echo "✓ Conexión a la base de datos establecida\n\n";
} catch (PDOException $e) {
    die("✗ Error de conexión: " . $e->getMessage() . "\n");
}

// Obtener todos los usuarios
try {
    $stmt = $pdo->query("SELECT id, user, password, name, lastname FROM users WHERE status = 1");
    $users = $stmt->fetchAll();

    echo "Usuarios encontrados: " . count($users) . "\n";
    echo str_repeat("-", 80) . "\n\n";
} catch (PDOException $e) {
    die("✗ Error al obtener usuarios: " . $e->getMessage() . "\n");
}

// Contadores
$migrated = 0;
$failed = 0;
$skipped = 0;

// Procesar cada usuario
foreach ($users as $user) {
    echo "Procesando usuario: {$user['user']} (ID: {$user['id']}) - {$user['name']} {$user['lastname']}\n";

    try {
        // Intentar desencriptar la contraseña del sistema anterior
        $decrypted_password = OldEncrypter::decrypt($user['password']);

        if (empty($decrypted_password)) {
            echo "  ⚠ La contraseña no pudo ser desencriptada (posiblemente ya está en bcrypt)\n";
            $skipped++;
            continue;
        }

        echo "  → Contraseña desencriptada exitosamente\n";

        // Encriptar con bcrypt (Laravel)
        $new_password = password_hash($decrypted_password, PASSWORD_BCRYPT, ['cost' => 10]);

        // Actualizar en la base de datos
        $update_stmt = $pdo->prepare("UPDATE users SET password = :password WHERE id = :id");
        $update_stmt->execute([
            ':password' => $new_password,
            ':id' => $user['id']
        ]);

        echo "  ✓ Contraseña migrada a bcrypt exitosamente\n";
        $migrated++;

    } catch (Exception $e) {
        echo "  ✗ Error: " . $e->getMessage() . "\n";
        $failed++;
    }

    echo "\n";
}

// Resumen
echo str_repeat("=", 80) . "\n";
echo "RESUMEN DE MIGRACIÓN\n";
echo str_repeat("=", 80) . "\n";
echo "✓ Migradas exitosamente: {$migrated}\n";
echo "⚠ Omitidas (ya en bcrypt): {$skipped}\n";
echo "✗ Fallidas: {$failed}\n";
echo str_repeat("=", 80) . "\n";

if ($migrated > 0) {
    echo "\n¡Migración completada! Las contraseñas han sido actualizadas a bcrypt.\n";
    echo "Los usuarios pueden seguir usando sus mismas contraseñas para iniciar sesión.\n";
}
