<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use Illuminate\Support\Facades\Log;

class BackupController extends Controller
{
    public function backup()
    {
        try {
            $database = env('DB_DATABASE');
            $username = env('DB_USERNAME');
            $password = env('DB_PASSWORD');
            $host = env('DB_HOST', '127.0.0.1');
            $port = env('DB_PORT', '3306');
            $mysqldumpPath = env('MYSQLDUMP_PATH', 'mysqldump');

            $backupDir = storage_path('backups');
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }

            $filename = 'backup_' . $database . '_' . date('Y-m-d_H-i-s') . '.sql';
            $filePath = $backupDir . DIRECTORY_SEPARATOR . $filename;

            // Construir comando para Windows con redirección directa al archivo
            $command = sprintf(
                '"%s" --host=%s --port=%s --user=%s --password=%s --single-transaction --routines --triggers %s > "%s" 2>&1',
                $mysqldumpPath,
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($username),
                escapeshellarg($password),
                escapeshellarg($database),
                $filePath
            );

            // Ejecutar comando
            $output = [];
            $returnVar = 0;
            exec($command, $output, $returnVar);

            // Verificar si el archivo fue creado y tiene contenido
            if (!file_exists($filePath) || filesize($filePath) === 0) {
                $errorMessage = implode("\n", $output);
                Log::error('Backup failed: ' . $errorMessage);

                // Limpiar archivo vacío si existe
                if (file_exists($filePath)) {
                    unlink($filePath);
                }

                return ApiResponse::create(
                    'Error al crear el backup',
                    500,
                    ['error' => $errorMessage ?: 'No se pudo crear el archivo de backup']
                );
            }

            // Verificar si el archivo contiene error en lugar de datos
            $fileContent = file_get_contents($filePath, false, null, 0, 500);
            if (strpos($fileContent, 'mysqldump: Got error') !== false || strpos($fileContent, 'Access denied') !== false) {
                $errorMessage = $fileContent;
                unlink($filePath);
                Log::error('Backup failed: ' . $errorMessage);
                return ApiResponse::create(
                    'Error al crear el backup',
                    500,
                    ['error' => $errorMessage]
                );
            }

            $fileSize = filesize($filePath);
            $fileSizeFormatted = $this->formatBytes($fileSize);

            return ApiResponse::create(
                'Backup creado correctamente',
                200,
                [
                    'filename' => $filename,
                    'path' => $filePath,
                    'size' => $fileSizeFormatted,
                    'created_at' => date('Y-m-d H:i:s'),
                ]
            );
        } catch (\Exception $e) {
            Log::error('Backup exception: ' . $e->getMessage());
            return ApiResponse::create(
                'Error al crear el backup',
                500,
                ['error' => $e->getMessage()]
            );
        }
    }

    public function cleanOldBackups()
    {
        try {
            $backupDir = storage_path('backups');

            if (!is_dir($backupDir)) {
                return ApiResponse::create(
                    'No hay backups para limpiar',
                    200,
                    ['deleted' => 0]
                );
            }

            $files = glob($backupDir . DIRECTORY_SEPARATOR . '*.sql');
            $oneWeekAgo = strtotime('-1 week');
            $deletedCount = 0;
            $deletedFiles = [];

            foreach ($files as $file) {
                if (filemtime($file) < $oneWeekAgo) {
                    $filename = basename($file);
                    unlink($file);
                    $deletedFiles[] = $filename;
                    $deletedCount++;
                }
            }

            return ApiResponse::create(
                'Backups antiguos eliminados correctamente',
                200,
                [
                    'deleted' => $deletedCount,
                    'files' => $deletedFiles,
                ]
            );
        } catch (\Exception $e) {
            Log::error('Clean old backups exception: ' . $e->getMessage());
            return ApiResponse::create(
                'Error al limpiar backups antiguos',
                500,
                ['error' => $e->getMessage()]
            );
        }
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
