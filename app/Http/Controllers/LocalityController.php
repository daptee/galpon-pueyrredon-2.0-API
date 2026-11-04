<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\Locality;
use Exception;
use Illuminate\Http\Request;

class LocalityController extends Controller
{
    // GET BY ID - Obtener una localidad en particular
    public function show(Request $request, $id)
    {
        try {
            $locality = Locality::with('province')->find($id);

            if (!$locality) {
                return ApiResponse::create('Localidad no encontrada', 404, [], [
                    'request' => $request,
                    'module' => 'locality',
                    'endpoint' => 'Obtener una localidad',
                ]);
            }

            return ApiResponse::create('Localidad obtenida correctamente', 200, $locality, [
                'request' => $request,
                'module' => 'locality',
                'endpoint' => 'Obtener una localidad',
            ]);
        } catch (Exception $e) {
            return ApiResponse::exception('Error al obtener la localidad', $e, [
                'request' => $request,
                'module' => 'locality',
                'endpoint' => 'Obtener una localidad',
            ]);
        }
    }
}
