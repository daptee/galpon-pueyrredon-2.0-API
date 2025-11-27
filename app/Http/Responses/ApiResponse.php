<?php
namespace App\Http\Responses;

use App\Http\Audith\AudithHelper;
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