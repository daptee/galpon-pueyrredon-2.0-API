<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MigrateUserPasswords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:migrate-passwords
                            {--dry-run : Ejecutar sin hacer cambios reales}
                            {--force : Forzar ejecución en producción}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrar contraseñas de usuarios de AES-256-CBC a bcrypt';

    /**
     * Configuración de encriptación del sistema anterior
     */
    private const ENCRYPT_METHOD = "AES-256-CBC";
    private const SECRET_KEY = 'galpo-pueyrredon_secretKey!';
    private const SECRET_IV = 'galpo-pueyrredon_secretIV!';

    /**
     * Estadísticas de migración
     */
    private $migrated = 0;
    private $skipped = 0;
    private $failed = 0;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Verificar si estamos en producción
        if (app()->environment('production') && !$this->option('force')) {
            $this->error('Este comando no puede ejecutarse en producción sin el flag --force');
            $this->warn('Asegúrate de hacer un backup antes de ejecutar.');
            return 1;
        }

        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('🔍 Modo DRY-RUN: No se harán cambios en la base de datos');
        } else {
            $this->warn('⚠️  ADVERTENCIA: Este comando modificará las contraseñas en la base de datos');

            if (!$this->confirm('¿Has hecho un backup de la base de datos?')) {
                $this->error('Abortado. Por favor haz un backup primero.');
                return 1;
            }
        }

        $this->info('');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('            MIGRACIÓN DE CONTRASEÑAS DE USUARIOS');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('');

        // Obtener usuarios activos
        $users = DB::table('users')
            ->where('status', 1)
            ->select('id', 'user', 'password', 'name', 'lastname')
            ->get();

        $this->info("Usuarios encontrados: {$users->count()}");
        $this->info('');

        if ($users->isEmpty()) {
            $this->warn('No hay usuarios para migrar.');
            return 0;
        }

        // Procesar cada usuario
        $progressBar = $this->output->createProgressBar($users->count());
        $progressBar->start();

        foreach ($users as $user) {
            $this->processUser($user, $isDryRun);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->info('');
        $this->info('');

        // Mostrar resumen
        $this->showSummary($isDryRun);

        return 0;
    }

    /**
     * Procesar un usuario individual
     */
    private function processUser($user, $isDryRun)
    {
        try {
            // Verificar si la contraseña ya está en bcrypt
            if ($this->isBcryptHash($user->password)) {
                $this->skipped++;
                return;
            }

            // Intentar desencriptar la contraseña del sistema anterior
            $decryptedPassword = $this->decryptOldPassword($user->password);

            if (empty($decryptedPassword)) {
                $this->failed++;
                return;
            }

            // Encriptar con bcrypt
            $newPassword = Hash::make($decryptedPassword);

            // Actualizar en la base de datos (solo si no es dry-run)
            if (!$isDryRun) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['password' => $newPassword]);
            }

            $this->migrated++;

        } catch (\Exception $e) {
            $this->failed++;
        }
    }

    /**
     * Desencriptar contraseña usando el método del sistema anterior
     */
    private function decryptOldPassword($encryptedPassword)
    {
        try {
            $key = hash('sha256', self::SECRET_KEY);
            $iv = substr(hash('sha256', self::SECRET_IV), 0, 16);

            $decrypted = openssl_decrypt(
                base64_decode($encryptedPassword),
                self::ENCRYPT_METHOD,
                $key,
                0,
                $iv
            );

            return $decrypted;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Verificar si una contraseña ya está en formato bcrypt
     */
    private function isBcryptHash($password)
    {
        // Los hashes bcrypt comienzan con $2y$ o $2a$ o $2b$
        return preg_match('/^\$2[ayb]\$.{56}$/', $password) === 1;
    }

    /**
     * Mostrar resumen de la migración
     */
    private function showSummary($isDryRun)
    {
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('                    RESUMEN DE MIGRACIÓN');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('');

        // Migradas exitosamente
        if ($this->migrated > 0) {
            $this->info("✓ Migradas exitosamente: {$this->migrated}");
        }

        // Omitidas (ya en bcrypt)
        if ($this->skipped > 0) {
            $this->warn("⚠ Omitidas (ya en bcrypt): {$this->skipped}");
        }

        // Fallidas
        if ($this->failed > 0) {
            $this->error("✗ Fallidas: {$this->failed}");
        }

        $this->info('');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        if ($isDryRun) {
            $this->info('');
            $this->comment('Esto fue una simulación (--dry-run). No se realizaron cambios.');
            $this->comment('Para ejecutar la migración real, ejecuta el comando sin --dry-run');
        } elseif ($this->migrated > 0) {
            $this->info('');
            $this->comment('¡Migración completada!');
            $this->comment('Las contraseñas han sido actualizadas a bcrypt.');
            $this->comment('Los usuarios pueden seguir usando sus mismas contraseñas para iniciar sesión.');
        }

        $this->info('');
    }
}
