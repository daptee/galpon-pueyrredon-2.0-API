<?php

namespace App\Http\Controllers;

use App\Models\EventType;
use App\Models\LogisticsSheet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;

class LogisticsSheetPublicController extends Controller
{
    private function editCutoffDate(LogisticsSheet $logisticsSheet): ?\Illuminate\Support\Carbon
    {
        if (!$logisticsSheet->event_start_datetime) {
            return null;
        }
        $days = (int) env('LOGISTICS_SHEET_EDIT_CUTOFF_DAYS', 2);
        return $logisticsSheet->event_start_datetime->copy()->subDays($days);
    }

    private function isReadOnly(LogisticsSheet $logisticsSheet): bool
    {
        $cutoff = $this->editCutoffDate($logisticsSheet);
        return $cutoff !== null && now()->greaterThan($cutoff);
    }

    public function show(Request $request, $token)
    {
        $logisticsSheet = LogisticsSheet::where('token', $token)->first();

        if (!$logisticsSheet) {
            return response()->json(['code' => 0, 'response' => 'Ficha logística no encontrada'], 404);
        }

        $logisticsSheet->load('budget.client', 'eventType');

        $budget = $logisticsSheet->budget;
        $budgetPdfPath = "storage/budgets/budget-{$budget->id}.pdf";

        return response()->json([
            'code' => 1,
            'response' => 'ok',
            'data' => [
                'logistics_sheet' => $logisticsSheet,
                'read_only' => $this->isReadOnly($logisticsSheet),
                'budget' => [
                    'id' => $budget->id,
                    'client_name' => $budget->client_name ?? optional($budget->client)->name,
                    'date_event' => $budget->date_event,
                    'time_event' => $budget->time_event,
                    'pdf_url' => file_exists(public_path($budgetPdfPath)) ? asset($budgetPdfPath) : null,
                ],
                'event_types' => EventType::all(['id', 'name']),
            ],
        ]);
    }

    public function update(Request $request, $token)
    {
        try {
            $logisticsSheet = LogisticsSheet::where('token', $token)->first();

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
                'event_start_datetime' => 'sometimes|nullable|date',
                'event_end_datetime' => 'sometimes|nullable|date',
                'address' => 'sometimes|nullable|string|max:255',
                'address_maps_link' => 'sometimes|nullable|string|max:500',
                'accessibility_comments' => 'sometimes|nullable|string|max:500',
                'order_contact_name' => 'sometimes|nullable|string|max:255',
                'order_contact_phone' => 'sometimes|nullable|string|max:20',
                'delivery_windows' => 'sometimes|nullable|array|max:3',
                'delivery_windows.*.date' => 'nullable|date',
                'delivery_windows.*.time_from' => 'nullable|date_format:H:i',
                'delivery_windows.*.time_to' => 'nullable|date_format:H:i',
                'pickup_windows' => 'sometimes|nullable|array|max:3',
                'pickup_windows.*.date' => 'nullable|date',
                'pickup_windows.*.time_from' => 'nullable|date_format:H:i',
                'pickup_windows.*.time_to' => 'nullable|date_format:H:i',
                'reception_contact_name' => 'sometimes|nullable|string|max:255',
                'reception_contact_phone' => 'sometimes|nullable|string|max:20',
                'cushion_color' => 'sometimes|nullable|string|max:100',
                'additional_order_details' => 'sometimes|nullable|string|max:500',
                'insurance_required' => 'sometimes|nullable|in:yes,not_applicable,later',
                'additional_requirements' => 'sometimes|nullable|string|max:500',
                'field_status' => 'sometimes|array',
                'field_status.*.field' => 'required_with:field_status|in:' . implode(',', array_keys(LogisticsSheet::GROUP_FIELDS)),
                'field_status.*.status' => 'required_with:field_status|in:later,not_applicable,completed',
                'insurance_document' => 'sometimes|file|max:10240',
                'assembly_plan_document' => 'sometimes|file|max:10240',
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
                'id_event_type',
                'event_type_other',
                'event_start_datetime',
                'event_end_datetime',
                'address',
                'address_maps_link',
                'accessibility_comments',
                'order_contact_name',
                'order_contact_phone',
                'delivery_windows',
                'pickup_windows',
                'reception_contact_name',
                'reception_contact_phone',
                'cushion_color',
                'additional_order_details',
                'insurance_required',
                'additional_requirements',
            ]));

            $this->storeAttachment($request, $logisticsSheet, 'insurance_document', 'insurance_document_path');
            $this->storeAttachment($request, $logisticsSheet, 'assembly_plan_document', 'assembly_plan_path');

            $this->applyFieldStatus($request, $logisticsSheet);

            $logisticsSheet->recalculateCompletion();
            $logisticsSheet->save();

            return response()->json([
                'code' => 1,
                'response' => 'Ficha logística actualizada correctamente',
                'data' => $logisticsSheet,
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

        $filename = time() . '_' . uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
        $file->move($storagePath, $filename);

        $logisticsSheet->{$column} = "storage/logistics_sheets/{$logisticsSheet->id_budget}/{$filename}";
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
}
