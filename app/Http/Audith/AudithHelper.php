<?php

namespace App\Http\Audith;

use Closure;
use Illuminate\Http\Request;
use App\Models\Audith;
use Log;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AudithHelper
{
    public static function create($audith, $response, $status)
    {
        try {
            // Obtener el usuario autenticado con JWT
            $user = null;
            try {
                $user = JWTAuth::parseToken()->authenticate();
            } catch (\Exception $e) {
                Log::warning("No se pudo autenticar el usuario en la auditoría: " . $e->getMessage());
            }

            Log::info($audith);

            // Construir la estructura de auditoría
            $auditData = [
                'module' => $audith['module'],
                'title' => $audith['endpoint'],
                'response' => $response, // Excluir contraseñas
                'request' => json_encode([
                    'status' => $status,
                    'body' => empty($audith['request']->getContent()) || $audith['request']->getContent() === 'null'
                        ? 'Ruta: ' . $_SERVER['REQUEST_URI']  // Si el body está vacío o es 'null', guarda solo la ruta
                        : array_diff_key(
                            json_decode($audith['request']->getContent(), true) ?? [],
                            ['password' => ''] // Elimina la contraseña
                        )
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), // Evita caracteres escapados
                'status' => $status
            ];
            
            // Verifica que el JSON no esté escapado en exceso antes de guardar
            $auditData['request'] = stripslashes($auditData['request']); // Elimina escapes innecesarios                                        

            Log::info(json_encode($auditData));

            // Registrar en la base de datos
            Audith::create([
                'id_user' => $user ? $user->id : $response['data']['id'], // Asigna el ID del usuario si está autenticado
                'data' => json_encode($auditData),
                'ip' => json_encode(['ip' => $audith['request']->ip()])
            ]);
        } catch (\Exception $e) {
            Log::error("Error al registrar auditoría: " . $e->getMessage());
        }

        return $audith;
    }
}
