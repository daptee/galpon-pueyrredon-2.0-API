<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Buscador global del sistema.
 *
 * Cada módulo se define en modules() con:
 *  - label:   nombre para mostrar en el front
 *  - group:   'main' (módulos principales), 'config' (tablas auxiliares de
 *             Configuración) o 'help' (tutoriales)
 *  - query:   fn(?User) => Builder con los joins y el select del módulo
 *  - columns: columnas donde se busca con LIKE
 *  - ids:     columnas que se comparan por igualdad cuando la palabra es un número
 *             (ej. "#123" o "123" encuentra el presupuesto 123)
 *  - extra:   fn(Builder $q, string $like, ?int $number) para condiciones extra
 *             (ej. buscar en los contactos de un cliente)
 *  - order:   [columna, dirección]
 *  - map:     fn(object $row) => ['title', 'subtitle', 'status', 'meta']
 *
 * La búsqueda separa el texto en palabras: cada palabra tiene que aparecer en
 * alguna de las columnas del módulo (AND entre palabras, OR entre columnas).
 * Así "juan perez" encuentra a un cliente con name=Juan y lastname=Perez.
 */
class GlobalSearchService
{
    public const MIN_LENGTH = 2;
    public const DEFAULT_LIMIT = 5;
    public const MAX_LIMIT = 20;
    private const MAX_WORDS = 5;

    /**
     * @param  string        $term   Texto buscado
     * @param  int           $limit  Resultados por módulo
     * @param  string[]|null $only   Limitar a estos módulos (keys de modules())
     * @param  User|null     $user   Usuario logueado (para visibilidad de tutoriales)
     */
    public function search(string $term, int $limit = self::DEFAULT_LIMIT, ?array $only = null, ?User $user = null): array
    {
        $words = $this->words($term);
        $results = [];
        $total = 0;

        foreach ($this->modules() as $key => $module) {
            if ($only && !in_array($key, $only, true)) {
                continue;
            }

            $query = ($module['query'])($user);
            $this->applyWords($query, $module, $words);

            [$orderColumn, $orderDirection] = $module['order'];

            // Se trae uno de más para saber si hay más resultados sin hacer un COUNT
            $rows = $query->orderBy($orderColumn, $orderDirection)->limit($limit + 1)->get();

            if ($rows->isEmpty()) {
                continue;
            }

            $hasMore = $rows->count() > $limit;
            $items = $rows->take($limit)->map(function ($row) use ($module) {
                return array_merge(
                    ['id' => $row->id],
                    array_merge(['subtitle' => null, 'status' => null, 'meta' => null], ($module['map'])($row))
                );
            })->values()->all();

            $total += count($items);
            $results[] = [
                'module' => $key,
                'label' => $module['label'],
                'group' => $module['group'],
                'has_more' => $hasMore,
                'items' => $items,
            ];
        }

        return [
            'query' => $term,
            'total' => $total,
            'results' => $results,
        ];
    }

    /** Keys y labels de los módulos disponibles (para el front / validación). */
    public function availableModules(): array
    {
        return collect($this->modules())
            ->map(fn ($m, $key) => ['module' => $key, 'label' => $m['label'], 'group' => $m['group']])
            ->values()
            ->all();
    }

    private function words(string $term): array
    {
        $words = preg_split('/\s+/u', trim($term), -1, PREG_SPLIT_NO_EMPTY);
        $words = array_map(fn ($w) => ltrim($w, '#'), $words);
        $words = array_values(array_filter($words, fn ($w) => $w !== ''));

        return array_slice($words, 0, self::MAX_WORDS);
    }

    private function applyWords(Builder $query, array $module, array $words): void
    {
        foreach ($words as $word) {
            $like = '%' . addcslashes($word, '%_\\') . '%';
            $number = ctype_digit($word) ? (int) $word : null;

            $query->where(function (Builder $q) use ($module, $like, $number) {
                foreach ($module['columns'] as $column) {
                    $q->orWhere($column, 'like', $like);
                }
                if ($number !== null) {
                    foreach ($module['ids'] ?? [] as $column) {
                        $q->orWhere($column, $number);
                    }
                }
                if (isset($module['extra'])) {
                    ($module['extra'])($q, $like, $number);
                }
            });
        }
    }

    private function modules(): array
    {
        return [
            // ───────────── Módulos principales ─────────────
            'users' => [
                'label' => 'Usuarios',
                'group' => 'main',
                'query' => fn () => DB::table('users as u')
                    ->leftJoin('user_types as ut', 'ut.id', '=', 'u.id_user_type')
                    ->leftJoin('status as s', 's.id', '=', 'u.status')
                    ->select('u.id', 'u.user', 'u.name', 'u.lastname', 'u.email', 'ut.name as user_type', 's.name as status_name'),
                'columns' => ['u.user', 'u.name', 'u.lastname', 'u.email', 'u.phone'],
                'ids' => ['u.id'],
                'order' => ['u.name', 'asc'],
                'map' => fn ($r) => [
                    'title' => $this->join(' ', [$r->name, $r->lastname]) ?: $r->user,
                    'subtitle' => $this->join(' · ', [$r->email, $r->user_type]),
                    'status' => $r->status_name,
                ],
            ],

            'clients' => [
                'label' => 'Clientes',
                'group' => 'main',
                'query' => fn () => DB::table('clients as c')
                    ->leftJoin('clients_types as ct', 'ct.id', '=', 'c.id_client_type')
                    ->leftJoin('status as s', 's.id', '=', 'c.status')
                    ->select('c.id', 'c.name', 'c.lastname', 'c.company', 'c.mail', 'c.phone', 'ct.name as client_type', 's.name as status_name'),
                'columns' => ['c.name', 'c.lastname', 'c.company', 'c.mail', 'c.phone', 'c.cuit', 'c.address'],
                'ids' => ['c.id'],
                // También busca en los contactos del cliente
                'extra' => function (Builder $q, string $like) {
                    $q->orWhereExists(function (Builder $sub) use ($like) {
                        $sub->selectRaw('1')
                            ->from('clients_contacts as cc')
                            ->whereColumn('cc.id_client', 'c.id')
                            ->where(function (Builder $w) use ($like) {
                                $w->where('cc.name', 'like', $like)
                                    ->orWhere('cc.lastname', 'like', $like)
                                    ->orWhere('cc.mail', 'like', $like)
                                    ->orWhere('cc.phone', 'like', $like);
                            });
                    });
                },
                'order' => ['c.name', 'asc'],
                'map' => fn ($r) => [
                    'title' => $this->join(' ', [$r->name, $r->lastname]),
                    'subtitle' => $this->join(' · ', [$r->company, $r->mail, $r->client_type]),
                    'status' => $r->status_name,
                ],
            ],

            'places' => [
                'label' => 'Lugares',
                'group' => 'main',
                'query' => fn () => DB::table('places as p')
                    ->leftJoin('places_types as pt', 'pt.id', '=', 'p.id_place_type')
                    ->leftJoin('localities as l', 'l.id', '=', 'p.id_locality')
                    ->leftJoin('status as s', 's.id', '=', 'p.status')
                    ->select('p.id', 'p.name', 'p.address', 'l.locality', 'pt.name as place_type', 's.name as status_name'),
                'columns' => ['p.name', 'p.address', 'p.phone', 'p.observations', 'l.locality'],
                'ids' => ['p.id'],
                'order' => ['p.name', 'asc'],
                'map' => fn ($r) => [
                    'title' => $r->name,
                    'subtitle' => $this->join(' · ', [$r->address, $r->locality, $r->place_type]),
                    'status' => $r->status_name,
                ],
            ],

            'transportations' => [
                'label' => 'Transportes',
                'group' => 'main',
                'query' => fn () => DB::table('transportations as t')
                    ->leftJoin('status as s', 's.id', '=', 't.status')
                    ->select('t.id', 't.name', 't.load_volume_up', 's.name as status_name'),
                'columns' => ['t.name'],
                'ids' => ['t.id'],
                'order' => ['t.name', 'asc'],
                'map' => fn ($r) => [
                    'title' => $r->name,
                    'subtitle' => $r->load_volume_up !== null ? 'Volumen de carga: ' . $r->load_volume_up : null,
                    'status' => $r->status_name,
                ],
            ],

            'products' => [
                'label' => 'Productos',
                'group' => 'main',
                'query' => fn () => DB::table('products as p')
                    ->leftJoin('product_lines as pl', 'pl.id', '=', 'p.id_product_line')
                    ->leftJoin('product_types as pt', 'pt.id', '=', 'p.id_product_type')
                    ->leftJoin('product_status as ps', 'ps.id', '=', 'p.id_product_status')
                    ->select('p.id', 'p.name', 'p.code', 'pl.name as line', 'pt.name as type', 'ps.name as status_name'),
                'columns' => ['p.name', 'p.code', 'p.description', 'pl.name', 'pt.name'],
                'ids' => ['p.id'],
                'order' => ['p.name', 'asc'],
                'map' => fn ($r) => [
                    'title' => $r->name,
                    'subtitle' => $this->join(' · ', [$r->code, $r->line, $r->type]),
                    'status' => $r->status_name,
                ],
            ],

            'budgets' => [
                'label' => 'Presupuestos',
                'group' => 'main',
                'query' => fn () => $this->budgetsQuery(),
                'columns' => $this->budgetColumns(),
                'ids' => ['b.id', 'b.id_budget'],
                'order' => ['b.id', 'desc'],
                'map' => fn ($r) => [
                    'title' => 'Presupuesto #' . $r->id . ($r->client_name ? ' - ' . $r->client_name : ''),
                    'subtitle' => $this->join(' · ', [$r->date_event, $r->place, $r->version_number ? 'Versión ' . $r->version_number : null]),
                    'status' => $r->status_name,
                    'meta' => ['id_budget' => $r->id_budget, 'date_event' => $r->date_event, 'total' => $r->total],
                ],
            ],

            // Un evento es un presupuesto aprobado (id_budget_status = 3), igual que en EventController
            'events' => [
                'label' => 'Eventos',
                'group' => 'main',
                'query' => fn () => $this->budgetsQuery()->where('b.id_budget_status', 3),
                'columns' => $this->budgetColumns(),
                'ids' => ['b.id'],
                // También busca en los datos de entrega (dirección y contactos)
                'extra' => function (Builder $q, string $like) {
                    $q->orWhereExists(function (Builder $sub) use ($like) {
                        $sub->selectRaw('1')
                            ->from('budget_delivery_data as bdd')
                            ->whereColumn('bdd.id_budget', 'b.id')
                            ->where(function (Builder $w) use ($like) {
                                $w->where('bdd.address', 'like', $like)
                                    ->orWhere('bdd.coordination_contact', 'like', $like)
                                    ->orWhere('bdd.reception_contact', 'like', $like);
                            });
                    });
                },
                'order' => ['b.date_event', 'desc'],
                'map' => fn ($r) => [
                    'title' => 'Evento #' . $r->id . ($r->client_name ? ' - ' . $r->client_name : ''),
                    'subtitle' => $this->join(' · ', [$r->date_event, $r->place]),
                    'status' => $r->status_name,
                    'meta' => ['date_event' => $r->date_event, 'total' => $r->total],
                ],
            ],

            'payments' => [
                'label' => 'Pagos',
                'group' => 'main',
                'query' => fn () => DB::table('payments as p')
                    ->leftJoin('budgets as b', 'b.id', '=', 'p.id_budget')
                    ->leftJoin('payment_types as pt', 'pt.id', '=', 'p.id_payment_type')
                    ->leftJoin('payment_methods as pm', 'pm.id', '=', 'p.id_payment_method')
                    ->leftJoin('payment_status as ps', 'ps.id', '=', 'p.id_payment_status')
                    ->select(
                        'p.id', 'p.id_budget', 'p.amount', 'p.payment_datetime',
                        'b.client_name', 'pt.name as payment_type', 'pm.name as payment_method', 'ps.name as status_name'
                    ),
                'columns' => ['p.observations', 'b.client_name', 'pt.name', 'pm.name'],
                'ids' => ['p.id', 'p.id_budget', 'p.amount'],
                'order' => ['p.payment_datetime', 'desc'],
                'map' => fn ($r) => [
                    'title' => 'Pago #' . $r->id . ' - $' . number_format((float) $r->amount, 2, ',', '.'),
                    'subtitle' => $this->join(' · ', [
                        'Presupuesto #' . $r->id_budget,
                        $r->client_name,
                        $r->payment_method,
                        $r->payment_datetime,
                    ]),
                    'status' => $r->status_name,
                    'meta' => ['id_budget' => $r->id_budget, 'amount' => $r->amount],
                ],
            ],

            // ───────────── Configuración (tablas auxiliares) ─────────────
            'user_types' => $this->simpleModule('user_types', 'Tipos de usuario'),
            'client_types' => $this->simpleModule('clients_types', 'Tipos de cliente'),
            'client_classes' => $this->simpleModule('clients_classes', 'Clases de cliente'),
            'place_types' => $this->simpleModule('places_types', 'Tipos de lugar'),
            'place_collection_types' => $this->simpleModule('places_collections_types', 'Tipos de cobro de lugar'),
            'place_areas' => $this->simpleModule('places_area', 'Áreas de lugar'),

            'tolls' => [
                'label' => 'Peajes',
                'group' => 'config',
                'query' => fn () => DB::table('tolls as t')
                    ->leftJoin('status as s', 's.id', '=', 't.status')
                    ->select('t.id', 't.name', 't.cost', 's.name as status_name'),
                'columns' => ['t.name'],
                'ids' => ['t.id'],
                'order' => ['t.name', 'asc'],
                'map' => fn ($r) => [
                    'title' => $r->name,
                    'subtitle' => $r->cost !== null ? 'Costo: $' . number_format((float) $r->cost, 2, ',', '.') : null,
                    'status' => $r->status_name,
                ],
            ],

            'client_place_transport_prices' => [
                'label' => 'Precios de traslado por cliente',
                'group' => 'config',
                'query' => fn () => DB::table('client_place_transport_prices as cpt')
                    ->leftJoin('clients as c', 'c.id', '=', 'cpt.id_client')
                    ->leftJoin('places as p', 'p.id', '=', 'cpt.id_place')
                    ->leftJoin('status as s', 's.id', '=', 'cpt.status')
                    ->select('cpt.id', 'c.name as client_name', 'c.lastname as client_lastname', 'p.name as place', 's.name as status_name'),
                'columns' => ['c.name', 'c.lastname', 'c.company', 'p.name', 'cpt.observations'],
                'ids' => ['cpt.id'],
                'order' => ['c.name', 'asc'],
                'map' => fn ($r) => [
                    'title' => $this->join(' - ', [$this->join(' ', [$r->client_name, $r->client_lastname]), $r->place]),
                    'status' => $r->status_name,
                ],
            ],

            'product_lines' => $this->simpleModule('product_lines', 'Líneas de producto'),
            'product_types' => $this->simpleModule('product_types', 'Tipos de producto'),
            'product_furnitures' => $this->simpleModule('product_furnitures', 'Mobiliarios'),
            'product_attributes' => $this->simpleModule('product_attributes', 'Atributos de producto'),
            'event_types' => $this->simpleModule('event_types', 'Tipos de evento'),
            'payment_types' => $this->simpleModule('payment_types', 'Tipos de pago'),
            'payment_methods' => $this->simpleModule('payment_methods', 'Métodos de pago'),
            'payment_statuses' => $this->simpleModule('payment_status', 'Estados de pago'),

            'blocked_dates' => [
                'label' => 'Fechas bloqueadas',
                'group' => 'config',
                'query' => fn () => DB::table('blocked_dates as bd')->select('bd.id', 'bd.date', 'bd.reason'),
                'columns' => ['bd.reason', 'bd.date'],
                'order' => ['bd.date', 'desc'],
                'map' => fn ($r) => [
                    'title' => $r->date,
                    'subtitle' => $r->reason,
                ],
            ],

            // ───────────── Ayuda (tutoriales) ─────────────
            // Los usuarios no internos solo ven lo activo/publicado (igual que TutorialController)
            'tutorial_modules' => [
                'label' => 'Módulos de tutoriales',
                'group' => 'help',
                'query' => fn (?User $user) => DB::table('tutorial_modules as tm')
                    ->select('tm.id', 'tm.name', 'tm.description')
                    ->when(!$user?->is_internal, fn ($q) => $q->where('tm.status', 1)),
                'columns' => ['tm.name', 'tm.description'],
                'order' => ['tm.order', 'asc'],
                'map' => fn ($r) => [
                    'title' => $r->name,
                    'subtitle' => $this->excerpt($r->description),
                ],
            ],

            'tutorial_subtopics' => [
                'label' => 'Subtemas de tutoriales',
                'group' => 'help',
                'query' => fn (?User $user) => DB::table('tutorial_subtopics as ts')
                    ->leftJoin('tutorial_modules as tm', 'tm.id', '=', 'ts.id_tutorial_module')
                    ->select('ts.id', 'ts.name', 'ts.description', 'ts.id_tutorial_module', 'tm.name as module_name')
                    ->when(!$user?->is_internal, fn ($q) => $q->where('ts.status', 1)->where('tm.status', 1)),
                'columns' => ['ts.name', 'ts.description'],
                'order' => ['ts.order', 'asc'],
                'map' => fn ($r) => [
                    'title' => $r->name,
                    'subtitle' => $r->module_name,
                    'meta' => ['id_tutorial_module' => $r->id_tutorial_module],
                ],
            ],

            'tutorials' => [
                'label' => 'Tutoriales',
                'group' => 'help',
                'query' => fn (?User $user) => DB::table('tutorial_items as ti')
                    ->leftJoin('tutorial_modules as tm', 'tm.id', '=', 'ti.id_tutorial_module')
                    ->leftJoin('tutorial_subtopics as ts', 'ts.id', '=', 'ti.id_tutorial_subtopic')
                    ->select('ti.id', 'ti.title', 'ti.id_tutorial_module', 'ti.id_tutorial_subtopic', 'tm.name as module_name', 'ts.name as subtopic_name')
                    ->when(!$user?->is_internal, fn ($q) => $q->where('ti.is_published', true)),
                'columns' => ['ti.title', 'ti.content'],
                'order' => ['ti.order', 'asc'],
                'map' => fn ($r) => [
                    'title' => $r->title,
                    'subtitle' => $this->join(' > ', [$r->module_name, $r->subtopic_name]),
                    'meta' => ['id_tutorial_module' => $r->id_tutorial_module, 'id_tutorial_subtopic' => $r->id_tutorial_subtopic],
                ],
            ],
        ];
    }

    /** Módulo de configuración que solo tiene name + status. */
    private function simpleModule(string $table, string $label): array
    {
        return [
            'label' => $label,
            'group' => 'config',
            'query' => fn () => DB::table($table . ' as x')
                ->leftJoin('status as s', 's.id', '=', 'x.status')
                ->select('x.id', 'x.name', 's.name as status_name'),
            'columns' => ['x.name'],
            'ids' => ['x.id'],
            'order' => ['x.name', 'asc'],
            'map' => fn ($r) => [
                'title' => $r->name,
                'status' => $r->status_name,
            ],
        ];
    }

    private function budgetsQuery(): Builder
    {
        return DB::table('budgets as b')
            ->leftJoin('budget_status as bs', 'bs.id', '=', 'b.id_budget_status')
            ->leftJoin('places as pl', 'pl.id', '=', 'b.id_place')
            ->leftJoin('clients as c', 'c.id', '=', 'b.id_client')
            ->select(
                'b.id', 'b.id_budget', 'b.client_name', 'b.date_event', 'b.version_number', 'b.total',
                'pl.name as place', 'bs.name as status_name'
            );
    }

    private function budgetColumns(): array
    {
        return ['b.client_name', 'b.client_mail', 'b.client_phone', 'b.observations', 'c.name', 'c.lastname', 'c.company', 'pl.name'];
    }

    private function join(string $glue, array $parts): string
    {
        return implode($glue, array_filter(array_map(fn ($p) => is_string($p) ? trim($p) : $p, $parts), fn ($p) => $p !== null && $p !== ''));
    }

    private function excerpt(?string $text, int $length = 120): ?string
    {
        if ($text === null) {
            return null;
        }
        $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($text)));

        return mb_strlen($plain) > $length ? mb_substr($plain, 0, $length) . '…' : $plain;
    }
}
