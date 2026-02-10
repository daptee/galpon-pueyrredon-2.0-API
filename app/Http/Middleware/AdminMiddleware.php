<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Log;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        try {
            // Verificar si el usuario está autenticado
            if (! $user = JWTAuth::parseToken()->authenticate()) {
                return ApiResponse::create('Credenciales no válidas', 401);
            }

            // Verificar si el usuario está activo
            if ($user->status !== 1) {
                return ApiResponse::create('Usuario deshabilitado', 403, 'El usuario se encuentra inactivo');
            }

            // Registrar última actividad
            $user->update(['last_activity' => now()]);
        } catch (Exception $e) {
            return ApiResponse::create('El token fallo', 401, $e->getMessage());
        }

        return $next($request);
    }
}

