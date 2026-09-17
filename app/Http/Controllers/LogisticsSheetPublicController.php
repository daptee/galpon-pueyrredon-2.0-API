<?php

namespace App\Http\Controllers;

use App\Models\BudgetDeliveryData;
use App\Models\EventType;
use App\Models\LogisticsSheet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class LogisticsSheetPublicController extends Controller
{
    // Campos que en realidad viven en budget_delivery_data (duplicados con
    // esa tabla), no en logistics_sheets. Ver LogisticsSheet::GROUP_FIELDS.
    private const DELIVERY_DATA_BOUND_FIELDS = [
        'id_event_type',
        'order_contact_name',
        'order_contact_phone',
        'reception_contact_name',
        'reception_contact_phone',
        'additional_order_details',
        'delivery_options',
    ];

    private function editCutoffDate(LogisticsSheet $logisticsSheet): ?\Illuminate\Support\Carbon
    {
        $budget = $logisticsSheet->budget;
        if (!$budget || !$budget->date_event) {
            return null;
        }
        $days = (int) env('LOGISTICS_SHEET_EDIT_CUTOFF_DAYS', 2);
        $eventDateTime = \Illuminate\Support\Carbon::parse($budget->date_event . ' ' . ($budget->time_event ?? '00:00:00'));
        return $eventDateTime->subDays($days);
    }

    private function isReadOnly(LogisticsSheet $logisticsSheet): bool
    {
        $cutoff = $this->editCutoffDate($logisticsSheet);
        return $cutoff !== null && now()->greaterThan($cutoff);
    }

    public function show(Request $request, $token)
    {
        $logisticsSheet = LogisticsSheet::with(
            'budget.client',
            'budget.place',
            'budget.budgetDeliveryData.eventType'
        )->where('token', $token)->first();

        if (!$logisticsSheet) {
            return response()->json(['code' => 0, 'response' => 'Ficha logística no encontrada'], 404);
        }

        $budget = $logisticsSheet->budget;
        $budgetPdfPath = "storage/budgets/budget-{$budget->id}.pdf";

        return response()->json([
            'code' => 1,
            'response' => 'ok',
            'data' => [
                'logistics_sheet' => $logisticsSheet->toPresentedArray(),
                'read_only' => $this->isReadOnly($logisticsSheet),
                'budget' => [
                    'id' => $budget->id,
                    'client_name' => $budget->client_name ?? optional($budget->client)->name,
                    'date_event' => $budget->date_event,
                    'time_event' => $budget->time_event,
                    'address' => optional($budget->place)->address,
                    'pdf_url' => file_exists(public_path($budgetPdfPath)) ? asset($budgetPdfPath) : null,
                ],
                'event_types' => EventType::all(['id', 'name', 'status']),
            ],
        ]);
    }

    public function update(Request $request, $token)
    {
        try {
            $logisticsSheet = LogisticsSheet::with('budget.place')->where('token', $token)->first();

            if (!$logisticsSheet) {
                return response()->json(['code' => 0, 'response' => 'Ficha logística no encontrada'], 404);
            }

            if ($this->isReadOnly($logisticsSheet)) {
                return response()->json(['code' => 0, 'response' => 'La ficha ya no admite modificaciones'], 403);
            }

            $data = $request->all();

            $validator = Validator::make($data, [
                'budget_ratified' => 'sometimes|boolean',
                'id_event_type' => 'sometimes|nullable|exists:event_types,id',
                'event_type_other' => 'sometimes|nullable|string|max:255',
                'event_end_datetime' => 'sometimes|nullable|date',
                'address_maps_link' => 'sometimes|nullable|string|max:500',
                'accessibility_comments' => 'sometimes|nullable|string|max:500',
                'order_contact_name' => 'sometimes|nullable|string|max:255',
                'order_contact_phone' => 'sometimes|nullable|string|max:20',
                'delivery_windows' => 'sometimes|nullable|array|max:3',
                'delivery_windows.*.datetime_from' => 'nullable|date',
                'delivery_windows.*.datetime_to' => 'nullable|date|after_or_equal:delivery_windows.*.datetime_from',
                'pickup_windows' => 'sometimes|nullable|array|max:3',
                'pickup_windows.*.datetime_from' => 'nullable|date',
                'pickup_windows.*.datetime_to' => 'nullable|date|after_or_equal:pickup_windows.*.datetime_from',
                'reception_contact_name' => 'sometimes|nullable|string|max:255',
                'reception_contact_phone' => 'sometimes|nullable|string|max:20',
                'cushion_color' => 'sometimes|nullable|string|max:100',
                'additional_order_details' => 'sometimes|nullable|string|max:500',
                'delivery_options' => 'sometimes|nullable|string|max:255',
                'insurance_required' => 'sometimes|nullable|in:yes,not_applicable,later',
                'insurance_request_text' => 'sometimes|nullable|string|max:1000',
                'additional_requirements' => 'sometimes|nullable|string|max:500',
                'completion_percentage' => 'sometimes|nullable|integer|min:0|max:100',
                'field_status' => 'sometimes|array',
                'field_status.*.field' => 'required_with:field_status|in:' . implode(',', array_keys(LogisticsSheet::GROUP_FIELDS)),
                'field_status.*.status' => 'required_with:field_status|in:later,not_applicable,completed',
                'insurance_document' => 'sometimes|file|max:10240',
                'insurance_additional_documents' => 'sometimes|array|max:5',
                'insurance_additional_documents.*' => 'file|max:10240',
                'assembly_plan_document' => 'sometimes|file|max:10240',
                'remove_insurance_document' => 'sometimes|boolean',
                'remove_assembly_plan_document' => 'sometimes|boolean',
                'remove_insurance_additional_documents' => 'sometimes|array',
                'remove_insurance_additional_documents.*' => 'string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => 0,
                    'response' => 'Error de validación',
                    'errors' => $validator->errors()->toArray(),
                ], 422);
            }

            $logisticsSheet->fill($request->only([
                'budget_ratified',
                'event_type_other',
                'event_end_datetime',
                'address_maps_link',
                'accessibility_comments',
                'delivery_windows',
                'pickup_windows',
                'cushion_color',
                'insurance_required',
                'insurance_request_text',
                'additional_requirements',
                'completion_percentage',
            ]));

            $this->applyFileRemovals($request, $logisticsSheet);

            $this->storeAttachment($request, $logisticsSheet, 'insurance_document', 'insurance_document_path');
            $this->storeAttachment($request, $logisticsSheet, 'assembly_plan_document', 'assembly_plan_path');
            $this->storeAdditionalInsuranceDocuments($request, $logisticsSheet);

            $this->applyFieldStatus($request, $logisticsSheet);

            $syncError = $this->syncBudgetDeliveryData($request, $logisticsSheet);
            if ($syncError) {
                return response()->json(['code' => 0, 'response' => $syncError['message']], $syncError['status']);
            }

            $logisticsSheet->recalculateCompletion();
            $logisticsSheet->save();

            return response()->json([
                'code' => 1,
                'response' => 'Ficha logística actualizada correctamente',
                'data' => $logisticsSheet->toPresentedArray(),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'code' => 0,
                'response' => 'Error al actualizar la ficha logística',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function storeAttachment(Request $request, LogisticsSheet $logisticsSheet, string $inputName, string $column): void
    {
        if (!$request->hasFile($inputName)) {
            return;
        }

        $file = $request->file($inputName);
        $storagePath = public_path("storage/logistics_sheets/{$logisticsSheet->id_budget}/");
        if (!file_exists($storagePath)) {
            mkdir($storagePath, 0777, true);
        }

        // Si había un archivo cargado antes en este mismo campo, lo borramos
        // del disco antes de guardar el nuevo (evita dejar huérfanos).
        $this->deleteStoredFile($logisticsSheet->{$column});

        $filename = time() . '_' . uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
        $file->move($storagePath, $filename);

        $logisticsSheet->{$column} = "storage/logistics_sheets/{$logisticsSheet->id_budget}/{$filename}";
    }

    private function deleteStoredFile(?string $relativePath): void
    {
        if (!$relativePath) {
            return;
        }
        $fullPath = public_path($relativePath);
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }
    }

    /**
     * Permite "quitar" adjuntos ya cargados: `remove_insurance_document` y
     * `remove_assembly_plan_document` (booleanos) vacían el archivo único
     * correspondiente; `remove_insurance_additional_documents[]` saca del
     * array los que coincidan por `path`. En los tres casos se borra también
     * el archivo físico del disco.
     */
    private function applyFileRemovals(Request $request, LogisticsSheet $logisticsSheet): void
    {
        if ($request->boolean('remove_insurance_document')) {
            $this->deleteStoredFile($logisticsSheet->insurance_document_path);
            $logisticsSheet->insurance_document_path = null;
        }

        if ($request->boolean('remove_assembly_plan_document')) {
            $this->deleteStoredFile($logisticsSheet->assembly_plan_path);
            $logisticsSheet->assembly_plan_path = null;
        }

        $pathsToRemove = $request->input('remove_insurance_additional_documents', []);
        if (!empty($pathsToRemove)) {
            $remaining = [];
            foreach ($logisticsSheet->insurance_additional_documents ?? [] as $document) {
                if (in_array($document['path'] ?? null, $pathsToRemove, true)) {
                    $this->deleteStoredFile($document['path'] ?? null);
                    continue;
                }
                $remaining[] = $document;
            }
            $logisticsSheet->insurance_additional_documents = $remaining;
        }
    }

    /**
     * `insurance_document` es la póliza principal (un solo archivo,
     * obligatorio si insurance_required=yes). `insurance_additional_documents`
     * es para sumar documentos de seguro extra (ART, seguro de vehículos,
     * etc.) — son opcionales y se van acumulando entre distintos guardados,
     * nunca se pisan entre sí.
     */
    private function storeAdditionalInsuranceDocuments(Request $request, LogisticsSheet $logisticsSheet): void
    {
        $files = $request->file('insurance_additional_documents', []);
        if (empty($files)) {
            return;
        }

        $storagePath = public_path("storage/logistics_sheets/{$logisticsSheet->id_budget}/");
        if (!file_exists($storagePath)) {
            mkdir($storagePath, 0777, true);
        }

        $documents = $logisticsSheet->insurance_additional_documents ?? [];

        foreach ($files as $file) {
            $originalName = $file->getClientOriginalName();
            $filename = time() . '_' . uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
            $file->move($storagePath, $filename);

            $documents[] = [
                'path' => "storage/logistics_sheets/{$logisticsSheet->id_budget}/{$filename}",
                'original_name' => $originalName,
            ];
        }

        $logisticsSheet->insurance_additional_documents = $documents;
    }

    private function applyFieldStatus(Request $request, LogisticsSheet $logisticsSheet): void
    {
        $fieldStatus = $logisticsSheet->field_status ?? [];

        foreach ($request->input('field_status', []) as $entry) {
            $field = $entry['field'];
            $status = $entry['status'];
            if ($status === 'later' || $status === 'not_applicable') {
                $fieldStatus[$field] = $status;
            } else {
                unset($fieldStatus[$field]);
            }
        }

        // Si el cliente envió valor real para las columnas de un grupo,
        // se considera resuelto y se limpia cualquier marca previa.
        foreach (LogisticsSheet::GROUP_FIELDS as $group => $columns) {
            $wasExplicitlyFlagged = collect($request->input('field_status', []))
                ->contains(fn ($entry) => $entry['field'] === $group);
            if ($wasExplicitlyFlagged) {
                continue;
            }
            $providedValue = collect($columns)->contains(fn ($column) => $request->has($column) || $request->hasFile($column));
            if ($providedValue) {
                unset($fieldStatus[$group]);
            }
        }

        $logisticsSheet->field_status = $fieldStatus;
    }

    /**
     * Escribe directo en budget_delivery_data (Ficha de Entrega) los campos
     * que son duplicados con la ficha logística (tipo de evento, contactos,
     * additional_order_details) — no se guardan en logistics_sheets.
     *
     * Para CREAR el registro hace falta un id_event_type y un id_locality
     * (aunque id_event_type ya admite null en updates posteriores, hace
     * falta uno real la primera vez): si todavía no hay forma de
     * resolverlos y el request está mandando alguno de estos campos, se
     * devuelve un error explícito en vez de perder la información en
     * silencio.
     *
     * @return array{message: string, status: int}|null null si se sincronizó
     *   bien (o no había nada que sincronizar en este request).
     */
    private function syncBudgetDeliveryData(Request $request, LogisticsSheet $logisticsSheet): ?array
    {
        $touchesDeliveryData = collect(self::DELIVERY_DATA_BOUND_FIELDS)
            ->contains(fn ($field) => $request->has($field));

        $budget = $logisticsSheet->budget;
        $place = optional($budget)->place;
        $existing = BudgetDeliveryData::where('id_budget', $logisticsSheet->id_budget)->first();

        if (!$existing && !$touchesDeliveryData) {
            // Nada de lo duplicado con budget_delivery_data se está
            // mandando en este request, y todavía no existe el registro:
            // no hay nada que sincronizar.
            return null;
        }

        $idEventType = $request->input('id_event_type', optional($existing)->id_event_type);
        $idLocality = optional($place)->id_locality ?? optional($existing)->id_locality;

        if (!$existing) {
            if (!$idEventType) {
                return [
                    'status' => 422,
                    'message' => 'No se pudo guardar el tipo de evento ni los contactos: elegí un tipo de evento del listado (con "otra opción" el sector operativo todavía tiene que cargar el tipo definitivo antes de poder registrar estos datos).',
                ];
            }
            if (!$idLocality) {
                return [
                    'status' => 422,
                    'message' => 'No se pudo guardar el tipo de evento ni los contactos: el presupuesto todavía no tiene un lugar (place) asignado. Contactá a Galpón Pueyrredón para que lo carguen.',
                ];
            }
        }

        // Estos se recalculan/derivan solos (no los manda el cliente
        // directamente), así que si no hay nada que poner se omiten en vez
        // de pisar con null lo que ya había.
        $mapped = array_filter([
            'id_locality' => $idLocality,
            'address' => optional($place)->address,
            'event_time' => optional($budget)->time_event ? substr($budget->time_event, 0, 5) : null,
            'additional_delivery_details' => $logisticsSheet->additional_requirements,
            'delivery_datetime' => $this->formatPrimaryWindow($logisticsSheet->delivery_windows),
            'widthdrawal_datetime' => $this->formatPrimaryWindow($logisticsSheet->pickup_windows),
        ], fn ($value) => $value !== null && $value !== '');

        // id_event_type ya es NULL-able en budget_delivery_data: si el
        // registro es nuevo siempre va (recién validado que no sea null más
        // arriba); si ya existe, solo se toca cuando la key vino explícita
        // en el request — así "id_event_type": null lo vacía a propósito
        // (por ejemplo, si el cliente pasa a usar "otra opción").
        if (!$existing || $request->has('id_event_type')) {
            $mapped['id_event_type'] = $idEventType;
        }

        // Estos sí los manda el cliente directamente: si el request trae la
        // key (aunque el valor sea null), se aplica tal cual — permite
        // vaciar el campo a propósito. Si el request no trae la key, no se
        // toca lo que ya estaba guardado (carga incremental).
        $directFields = [
            'order_contact_name' => 'coordination_contact',
            'order_contact_phone' => 'cellphone_coordination',
            'reception_contact_name' => 'reception_contact',
            'reception_contact_phone' => 'cellphone_reception',
            'additional_order_details' => 'additional_order_details',
            'delivery_options' => 'delivery_options',
        ];
        foreach ($directFields as $requestKey => $column) {
            if ($request->has($requestKey)) {
                $mapped[$column] = $request->input($requestKey);
            }
        }

        try {
            BudgetDeliveryData::updateOrCreate(['id_budget' => $logisticsSheet->id_budget], $mapped);
        } catch (Throwable $e) {
            // No dejamos que un problema al escribir el mirror (ej. una
            // columna legacy más chica de lo esperado) tire abajo el
            // guardado de la ficha logística en sí.
            Log::warning('No se pudo sincronizar budget_delivery_data desde la ficha logística', [
                'id_budget' => $logisticsSheet->id_budget,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * budget_delivery_data.delivery_datetime/widthdrawal_datetime son texto
     * libre pensado para UNA sola ventana (a diferencia de
     * logistics_sheets.delivery_windows/pickup_windows, que sí soporta hasta
     * 3). Por eso solo se espeja la primera opción (la obligatoria), nunca
     * las 3 concatenadas.
     */
    private function formatPrimaryWindow(?array $windows): ?string
    {
        $window = $windows[0] ?? null;
        $from = $window['datetime_from'] ?? null;
        $to = $window['datetime_to'] ?? null;
        if (!$from || !$to) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($from)->format('d/m/Y H:i')
                . ' a ' . \Illuminate\Support\Carbon::parse($to)->format('d/m/Y H:i');
        } catch (Throwable $e) {
            return null;
        }
    }
}
