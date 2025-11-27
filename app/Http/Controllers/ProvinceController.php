<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\Province;
use Exception;
use Illuminate\Http\Request;

class ProvinceController extends Controller
{
    // GET ALL - Listar todas las provincias con sus localidades
    public function index(Request $request)
    {
        try {
            $provinces = Province::with('localities')
                ->orderBy('province')
                ->get();

            return ApiResponse::create('Listado de provincias obtenido correctamente', 200, $provinces, [
                'request' => $request,
                'module' => 'province',
                'endpoint' => 'Obtener todas las provincias',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error al obtener las provincias', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'province',
                'endpoint' => 'Obtener todas las provincias',
            ]);
        }
    }

    // GET BY ID - Obtener una provincia en particular con sus localidades
    public function show(Request $request, $id)
    {
        try {
            $province = Province::with('localities')->find($id);

            if (!$province) {
                return ApiResponse::create('Provincia no encontrada', 404, [], [
                    'request' => $request,
                    'module' => 'province',
                    'endpoint' => 'Obtener una provincia',
                ]);
            }

            return ApiResponse::create('Provincia obtenida correctamente', 200, $province, [
                'request' => $request,
                'module' => 'province',
                'endpoint' => 'Obtener una provincia',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error al obtener la provincia', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'province',
                'endpoint' => 'Obtener una provincia',
            ]);
        }
    }
}
