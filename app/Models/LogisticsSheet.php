<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogisticsSheet extends Model
{
    protected $table = 'logistics_sheets';

    protected $fillable = [
        'id_budget',
        'token',
        'budget_ratified',
        'event_type_other',
        'event_end_datetime',
        'address_maps_link',
        'accessibility_comments',
        'delivery_windows',
        'pickup_windows',
        'cushion_color',
        'insurance_required',
        'insurance_document_path',
        'insurance_additional_documents',
        'insurance_request_text',
        'additional_requirements',
        'assembly_plan_path',
        'field_status',
        'completion_percentage',
        'is_completed',
        'completed_at',
    ];

    protected $casts = [
        'budget_ratified' => 'boolean',
        'is_completed' => 'boolean',
        'completion_percentage' => 'integer',
        'event_end_datetime' => 'datetime',
        'completed_at' => 'datetime',
        'delivery_windows' => 'array',
        'pickup_windows' => 'array',
        'field_status' => 'array',
        'insurance_additional_documents' => 'array',
    ];

    // Campos aceptados en field_status ('later' | 'not_applicable') y los
    // nombres de input (no siempre columnas propias, ver más abajo) que cada
    // uno agrupa. Si el cliente envía valor para alguno, la marca se limpia
    // automáticamente.
    //
    // 'event_start_datetime' y 'address' no están: se toman del presupuesto
    // (budget.date_event/time_event y budget.place.address).
    //
    // 'event_type', 'order_contact', 'reception_contact' y
    // 'additional_order_details' tampoco tienen columna propia acá: son
    // campos duplicados con budget_delivery_data (Ficha de Entrega), así que
    // se leen/escriben directo ahí (ver LogisticsSheetPublicController) para
    // no guardar la misma información en dos tablas.
    public const GROUP_FIELDS = [
        'budget_ratified' => ['budget_ratified'],
        'event_type' => ['id_event_type', 'event_type_other'],
        'event_end_datetime' => ['event_end_datetime'],
        'accessibility_comments' => ['accessibility_comments'],
        'order_contact' => ['order_contact_name', 'order_contact_phone'],
        'delivery_windows' => ['delivery_windows'],
        'pickup_windows' => ['pickup_windows'],
        'reception_contact' => ['reception_contact_name', 'reception_contact_phone'],
        'cushion_color' => ['cushion_color'],
        'additional_order_details' => ['additional_order_details'],
        'delivery_options' => ['delivery_options'],
        'insurance_required' => ['insurance_required'],
        // 'insurance_document' agrupa el archivo (input tal como llega en el
        // request, no la columna insurance_document_path) y su alternativa en
        // texto (insurance_request_text) — alcanza con uno de los dos.
        'insurance_document' => ['insurance_document', 'insurance_request_text'],
        'additional_requirements' => ['additional_requirements'],
        'assembly_plan' => ['assembly_plan_document'],
    ];

    public function budget()
    {
        return $this->belongsTo(Budget::class, 'id_budget');
    }

    private function fieldFlag(string $field): ?string
    {
        return $this->field_status[$field] ?? null;
    }

    private function isResolved(string $field, bool $hasValue): bool
    {
        $flag = $this->fieldFlag($field);
        if ($flag === 'later') {
            return false;
        }
        if ($flag === 'not_applicable') {
            return true;
        }
        return $hasValue;
    }

    private function hasFirstWindow(?array $windows): bool
    {
        return isset($windows[0]['datetime_from'], $windows[0]['datetime_to'])
            && $windows[0]['datetime_from'] && $windows[0]['datetime_to'];
    }

    private function hasBudgetEventStart(): bool
    {
        return (bool) ($this->budget && $this->budget->date_event);
    }

    private function hasBudgetAddress(): bool
    {
        return (bool) ($this->budget && $this->budget->place && $this->budget->place->address);
    }

    /**
     * Datos que hoy viven en budget_delivery_data (Ficha de Entrega) porque
     * son duplicados con esa tabla: tipo de evento, contacto del pedido,
     * contacto de recepción y detalles adicionales de armado/desarme.
     */
    private function deliveryData(): ?BudgetDeliveryData
    {
        return $this->budget ? $this->budget->budgetDeliveryData : null;
    }

    /**
     * Ficha completa para exponer por API: los campos propios más los que
     * viven en budget_delivery_data, presentados con los mismos nombres que
     * usaba antes la ficha (id_event_type, order_contact_name/phone,
     * reception_contact_name/phone, additional_order_details), para que el
     * frontend no tenga que enterarse de dónde se guarda cada uno.
     * Requiere `budget` (y, para 'event_type', `budget.budgetDeliveryData.eventType`)
     * ya cargados si se quiere evitar N+1.
     */
    public function toPresentedArray(): array
    {
        $deliveryData = $this->deliveryData();

        return array_merge($this->toArray(), [
            'id_event_type' => optional($deliveryData)->id_event_type,
            'event_type' => optional($deliveryData)->eventType,
            'order_contact_name' => optional($deliveryData)->coordination_contact,
            'order_contact_phone' => optional($deliveryData)->cellphone_coordination,
            'reception_contact_name' => optional($deliveryData)->reception_contact,
            'reception_contact_phone' => optional($deliveryData)->cellphone_reception,
            'additional_order_details' => optional($deliveryData)->additional_order_details,
            'delivery_options' => optional($deliveryData)->delivery_options,
        ]);
    }

    /**
     * Recalcula si la ficha está completa: todo campo mandatorio tiene valor
     * (o está marcado 'not_applicable') y ningún campo quedó marcado 'later'.
     */
    public function recalculateCompletion(): bool
    {
        if (in_array('later', $this->field_status ?? [], true)) {
            $this->is_completed = false;
            $this->completed_at = null;
            return false;
        }

        $deliveryData = $this->deliveryData();

        $mandatoryChecks = [
            'budget_ratified' => (bool) $this->budget_ratified,
            'event_type' => (bool) (optional($deliveryData)->id_event_type || $this->event_type_other),
            'event_start_datetime' => $this->hasBudgetEventStart(),
            'event_end_datetime' => (bool) $this->event_end_datetime,
            'address' => $this->hasBudgetAddress(),
            'order_contact' => (bool) (optional($deliveryData)->coordination_contact && optional($deliveryData)->cellphone_coordination),
            'delivery_windows' => $this->hasFirstWindow($this->delivery_windows),
            'pickup_windows' => $this->hasFirstWindow($this->pickup_windows),
            'reception_contact' => (bool) (optional($deliveryData)->reception_contact && optional($deliveryData)->cellphone_reception),
            'additional_order_details' => (bool) optional($deliveryData)->additional_order_details,
            'insurance_required' => (bool) $this->insurance_required,
            'insurance_document' => $this->insurance_required !== 'yes'
                || (bool) $this->insurance_document_path
                || (bool) $this->insurance_request_text,
        ];

        foreach ($mandatoryChecks as $field => $hasValue) {
            if (!$this->isResolved($field, $hasValue)) {
                $this->is_completed = false;
                $this->completed_at = null;
                return false;
            }
        }

        if (!$this->is_completed) {
            $this->completed_at = now();
        }
        $this->is_completed = true;
        return true;
    }
}
