<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use App\Models\Audith;

class AudithController extends Controller
{
    public function index(Request $request)
    {
        try {
            // Obtén la página y el número de resultados por página desde la query string con valores por defecto
            $perPage = $request->query('per_page', 30);
            $page = $request->query('page', 1);

            // Inicializa la consulta
            $query = Audith::query();

            // Aplicar filtros si existen
            if ($request->has('id_user')) {
                $query->where('id_user', $request->id_user);
            }

            if ($request->has('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            if ($request->has('search')) {
                $search = strtolower($request->search);
                $query->where(function ($q) use ($search) {
                    $q->whereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(data, '$.module'))) LIKE ?", ["%$search%"])
                        ->orWhereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(data, '$.title'))) LIKE ?", ["%$search%"]);
                });
            }

            if ($request->has('module')) {
                $query->whereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(data, '$.module'))) LIKE ?", ['%' . strtolower($request->module) . '%']);
            }

            if ($request->has('title')) {
                $query->whereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(data, '$.title'))) LIKE ?", ['%' . strtolower($request->title) . '%']);
            }

            if ($request->has('status')) {
                $query->whereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(data, '$.status'))) LIKE ?", ['%' . strtolower($request->status) . '%']);
            }

            // Ordenar por fecha de creación (más recientes primero)
            $query->orderBy('created_at', 'desc');

            // Paginación personalizada
            $audits = $query->paginate($perPage, ['*'], 'page', $page);

            // Convertir "data" e "ip" en JSON
            $data = $audits->getCollection()->map(function ($audit) {
                $decodedData = json_decode($audit->getAttribute('data'), true); // Decodificar "data"

                if (isset($decodedData['request']) && is_string($decodedData['request'])) {
                    $decodedData['request'] = json_decode($decodedData['request'], true);
                }

                $audit->setAttribute('data', $decodedData);
                $audit->setAttribute('ip', json_decode($audit->getAttribute('ip'), true));

                return $audit;
            });

            // Construcción de metadatos de paginación
            $meta_data = [
                'page' => $audits->currentPage(),
                'per_page' => $audits->perPage(),
                'total' => $audits->total(),
                'last_page' => $audits->lastPage(),
            ];

            return ApiResponse::paginate('Registros de auditoría obtenidos correctamente', 200, $data, $meta_data);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al obtener los registros de auditoría', 500, ['error' => $e->getMessage()]);
        }
    }

}
