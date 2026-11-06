<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Services\GlobalSearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GlobalSearchController extends Controller
{
    public function __construct(private GlobalSearchService $searchService)
    {
    }

    // GET /search?q=texto&limit=5&modules=clients,budgets
    public function search(Request $request)
    {
        $audith = [
            'request' => $request,
            'module' => 'search',
            'endpoint' => 'Buscador global',
        ];

        try {
            $validator = Validator::make($request->query(), [
                'q' => 'required|string|min:' . GlobalSearchService::MIN_LENGTH . '|max:100',
                'limit' => 'nullable|integer|min:1|max:' . GlobalSearchService::MAX_LIMIT,
                'modules' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, [$validator->errors()->toArray()], $audith);
            }

            $only = null;
            if ($request->filled('modules')) {
                $only = array_values(array_filter(array_map('trim', explode(',', $request->query('modules')))));
                $valid = array_column($this->searchService->availableModules(), 'module');
                $invalid = array_diff($only, $valid);

                if ($invalid) {
                    return ApiResponse::create('Error de validación', 422, [[
                        'modules' => ['Módulos inválidos: ' . implode(', ', $invalid)],
                    ]], $audith);
                }
            }

            $result = $this->searchService->search(
                trim($request->query('q')),
                (int) $request->query('limit', GlobalSearchService::DEFAULT_LIMIT),
                $only,
                auth()->user()
            );

            return ApiResponse::create('Búsqueda realizada correctamente', 200, $result, $audith);
        } catch (\Exception $e) {
            return ApiResponse::exception('Error al realizar la búsqueda', $e, $audith);
        }
    }

    // GET /search/modules - Lista de módulos en los que busca (para filtros del front)
    public function modules(Request $request)
    {
        return ApiResponse::create('Módulos de búsqueda traídos correctamente', 200, $this->searchService->availableModules(), [
            'request' => $request,
            'module' => 'search',
            'endpoint' => 'Módulos del buscador global',
        ]);
    }
}
