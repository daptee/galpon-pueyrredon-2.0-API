<?php
namespace App\Http\Responses;

use App\Http\Audith\AudithHelper;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;
class ApiResponse
{
    /**
     * Generate a standard API response.
     *
     * @param  string  $message
     * @param  int  $code
     * @param  mixed  $result
     * @return \Illuminate\Http\JsonResponse
     */
    public static function create($message, $code, $result = [], $audith = [])
    {
        $response = [
            'message' => $message,
            'data' => $result,
        ];
        if ($code !== 200 && $code !== 201) {
            $response['data'] = null;
            $response['error'] = $result;
        }
        AudithHelper::create($audith, $response, $code);
        return response()->json($response, $code);
    }

    /**
     * Respuesta de error a partir de una excepción capturada, con el código
     * HTTP que corresponde al tipo de error (no todo es un 500):
     * - ValidationException ($request->validate())     → 422
     * - ModelNotFoundException (findOrFail/firstOrFail) → 404
     * - AuthorizationException                         → 403
     * - HttpException (abort(4xx))                     → su código
     * - Violación de integridad (duplicado / FK)       → 409
     * - Cualquier otra                                 → 500
     */
    public static function exception($message, Throwable $e, $audith = [])
    {
        if ($e instanceof ValidationException) {
            return self::create('Error de validación', 422, [$e->errors()], $audith);
        }

        if ($e instanceof ModelNotFoundException) {
            return self::create('Registro no encontrado', 404, ['error' => 'Registro no encontrado'], $audith);
        }

        if ($e instanceof AuthorizationException) {
            return self::create('Acceso denegado', 403, ['error' => $e->getMessage()], $audith);
        }

        if ($e instanceof HttpExceptionInterface) {
            return self::create($message, $e->getStatusCode(), ['error' => $e->getMessage()], $audith);
        }

        if ($e instanceof QueryException && ($e->errorInfo[0] ?? null) === '23000') {
            return self::create($message, 409, ['error' => 'El registro está duplicado o tiene datos relacionados'], $audith);
        }

        Log::error($message . ': ' . $e->getMessage(), ['exception' => $e]);

        return self::create($message, 500, ['error' => $e->getMessage()], $audith);
    }

    public static function paginate($message, $code, $result = [], $meta = [], $audith = [])
    {
        $response = [
            'message' => $message,
            'data' => $result,
            'meta_data' => $meta,
        ];
        if ($code !== 200 && $code !== 201) {
            $response['data'] = null;
            $response['error'] = $result;
        }
        AudithHelper::create($audith, $response, $code);
        return response()->json($response, $code);
    }

    public static function login($message, $code, $result = [], $token, $audith = [])
    {
        $response = [
            'message' => $message,
            'data' => $result,
            'token' => $token,
        ];
        if ($code !== 200 && $code !== 201) {
            $response['data'] = null;
            $response['error'] = $result;
        }
        AudithHelper::create($audith, $response, $code);
        return response()->json($response, $code);
    }
}