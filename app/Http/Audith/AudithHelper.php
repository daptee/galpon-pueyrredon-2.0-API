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
            // Solo guardar auditorías en producción
            if (config('app.env') !== 'production') {
                return $audith;
            }

            // No guardar auditoría para respuestas 200 en peticiones GET
            $method = isset($audith['request']) && $audith['request'] instanceof Request
                ? $audith['request']->method()
                : request()->method();

            if ($status === 200 && strtoupper($method) === 'GET') {
                return $audith;
            }

            // Obtener el usuario autenticado con JWT
            $user = null;
            try {
                $user = JWTAuth::parseToken()->authenticate();
            } catch (\Exception $e) {
                Log::warning("No se pudo autenticar el usuario en la auditoría: " . $e->getMessage());
            }

            Log::info("requesss");
            Log::info(isset($audith['request']) ? 'Request presente' : 'Sin request');

            // Definir contenido del request
            if (isset($audith['request']) && $audith['request'] instanceof Request) {
                $content = $audith['request']->getContent();

                $bodyData = empty($content) || $content === null
                    ? 'Ruta: ' . ($_SERVER['REQUEST_URI'] ?? '')
                    : array_diff_key(
                        json_decode($content, true) ?? [],
                        ['password' => ''] // Elimina la contraseña
                    );
            } else {
                $bodyData = 'Ruta: ' . ($_SERVER['REQUEST_URI'] ?? '');
            }

            // Construir la estructura de auditoría
            $auditData = [
                'module'   => $audith['module'] ?? '',
                'title'    => $audith['endpoint'] ?? '',
                'response' => $response, // Excluir contraseñas
                'request'  => json_encode([
                    'status' => $status,
                    'body'   => $bodyData
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), // Evita caracteres escapados
                'status'   => $status
            ];

            // Eliminar escapes innecesarios
            $auditData['request'] = stripslashes($auditData['request']);

            // Registrar en la base de datos
            Audith::create([
                'id_user' => $user ? $user->id : ($response['data']['id'] ?? null), // Asigna el ID del usuario si está autenticado
                'data'    => json_encode($auditData),
                'ip'      => json_encode([
                'ip' => isset($audith['request']) && $audith['request'] instanceof Request
                    ? $audith['request']->ip()
                    : request()->ip()
                ])
            ]);
        } catch (\Exception $e) {
            Log::error("Error al registrar auditoría: " . $e->getMessage());
        }

        return $audith;
    }
}
