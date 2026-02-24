<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use App\Models\Audith;
use App\Models\User;

class AudithController extends Controller
{
    /**
     * Mapa de módulos a nombres legibles en español.
     */
    private array $moduleLabels = [
        'budget'          => 'Presupuesto',
        'budgets'         => 'Presupuesto',
        'user'            => 'Usuario',
        'auth'            => 'Autenticación',
        'client'          => 'Cliente',
        'product'         => 'Producto',
        'products'        => 'Producto',
        'payment'         => 'Pago',
        'place'           => 'Lugar',
        'places'          => 'Lugar',
        'event'           => 'Evento',
        'transportation'  => 'Transporte',
        'audith'          => 'Auditoría',
    ];

    /**
     * Mapa de códigos HTTP a descripciones legibles.
     */
    private array $statusLabels = [
        200 => 'Éxito',
        201 => 'Creado correctamente',
        400 => 'Solicitud inválida',
        401 => 'No autorizado',
        403 => 'Acceso denegado',
        404 => 'No encontrado',
        422 => 'Error de validación',
        500 => 'Error interno del servidor',
    ];

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

    /**
     * Retorna la auditoría completa de un usuario en un rango de fechas,
     * con términos legibles para el usuario final.
     *
     * GET /audith/user/{id}?date_from=YYYY-MM-DD&date_to=YYYY-MM-DD
     */
    public function userAudit(Request $request, int $id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return ApiResponse::create('Usuario no encontrado', 404, ['error' => "No existe un usuario con id $id"]);
            }

            $query = Audith::where('id_user', $id);

            if ($request->has('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $query->orderBy('created_at', 'desc');

            $perPage  = $request->query('per_page', 30);
            $page     = $request->query('page', 1);
            $paginated = $query->paginate($perPage, ['*'], 'page', $page);

            $records = $paginated->getCollection()->map(function ($audit) {
                $raw  = json_decode($audit->getAttribute('data'), true) ?? [];
                $ipRaw = json_decode($audit->getAttribute('ip'), true) ?? [];

                // Decodificar request anidado si viene como string
                if (isset($raw['request']) && is_string($raw['request'])) {
                    $raw['request'] = json_decode($raw['request'], true) ?? [];
                }

                $statusCode = $raw['status'] ?? null;
                $body       = $raw['request']['body'] ?? null;

                return [
                    'id'          => $audit->id,
                    'fecha'       => \Carbon\Carbon::parse($audit->created_at)
                                        ->setTimezone('America/Argentina/Buenos_Aires')
                                        ->format('d/m/Y H:i:s'),
                    'modulo'      => $this->resolveModuleLabel($raw['module'] ?? ''),
                    'accion'      => $raw['title'] ?? 'Sin descripción',
                    'resultado'   => $this->resolveStatusLabel($statusCode),
                    'codigo_http' => $statusCode,
                    'ip'          => $ipRaw['ip'] ?? 'Desconocida',
                    'detalle'     => $this->formatBody($body),
                ];
            });

            $userData = [
                'id'       => $user->id,
                'nombre'   => trim($user->name . ' ' . $user->lastname),
                'usuario'  => $user->user,
                'email'    => $user->email,
            ];

            $meta = [
                'page'      => $paginated->currentPage(),
                'per_page'  => $paginated->perPage(),
                'total'     => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ];

            return ApiResponse::paginate(
                'Auditoría del usuario obtenida correctamente',
                200,
                ['usuario' => $userData, 'registros' => $records],
                $meta
            );
        } catch (\Exception $e) {
            return ApiResponse::create('Error al obtener la auditoría del usuario', 500, ['error' => $e->getMessage()]);
        }
    }

    private function resolveModuleLabel(string $module): string
    {
        $key = strtolower(trim($module));
        return $this->moduleLabels[$key] ?? ucfirst($module) ?: 'Sin módulo';
    }

    private function resolveStatusLabel(?int $code): string
    {
        if ($code === null) {
            return 'Desconocido';
        }
        return $this->statusLabels[$code] ?? "HTTP $code";
    }

    private function formatBody(mixed $body): ?string
    {
        if ($body === null || $body === '') {
            return null;
        }

        if (is_string($body)) {
            return $body;
        }

        if (is_array($body)) {
            // Eliminar campos técnicos / sensibles antes de mostrar
            $excluded = ['password', 'password_confirmation', 'token'];
            $clean    = array_diff_key($body, array_flip($excluded));

            if (empty($clean)) {
                return null;
            }

            // Convertir a texto legible: "campo: valor, campo: valor"
            $parts = [];
            foreach ($clean as $key => $value) {
                $label   = ucwords(str_replace('_', ' ', $key));
                $display = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
                $parts[] = "$label: $display";
            }
            return implode(' | ', $parts);
        }

        return null;
    }

}
