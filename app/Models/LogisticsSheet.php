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
        'insurance_document_path',
        'additional_requirements',
        'assembly_plan_path',
        'field_status',
        'is_completed',
        'completed_at',
    ];

    protected $casts = [
        'budget_ratified' => 'boolean',
        'is_completed' => 'boolean',
        'event_start_datetime' => 'datetime',
        'event_end_datetime' => 'datetime',
        'completed_at' => 'datetime',
        'delivery_windows' => 'array',
        'pickup_windows' => 'array',
        'field_status' => 'array',
    ];

    // Campos aceptados en field_status ('later' | 'not_applicable') y las
    // columnas del modelo que cada uno agrupa. Si el cliente envía valor para
    // alguna de esas columnas, la marca se limpia automáticamente.
    public const GROUP_FIELDS = [
        'budget_ratified' => ['budget_ratified'],
        'event_type' => ['id_event_type', 'event_type_other'],
        'event_start_datetime' => ['event_start_datetime'],
        'event_end_datetime' => ['event_end_datetime'],
        'address' => ['address'],
        'accessibility_comments' => ['accessibility_comments'],
        'order_contact' => ['order_contact_name', 'order_contact_phone'],
        'delivery_windows' => ['delivery_windows'],
        'pickup_windows' => ['pickup_windows'],
        'reception_contact' => ['reception_contact_name', 'reception_contact_phone'],
        'cushion_color' => ['cushion_color'],
        'additional_order_details' => ['additional_order_details'],
        'insurance_required' => ['insurance_required'],
        // Estos dos apuntan al nombre del campo de archivo tal como llega en
        // el request (no a la columna donde se guarda la ruta ya movida).
        'insurance_document' => ['insurance_document'],
        'additional_requirements' => ['additional_requirements'],
        'assembly_plan' => ['assembly_plan_document'],
    ];

    public function budget()
    {
        return $this->belongsTo(Budget::class, 'id_budget');
    }

    public function eventType()
    {
        return $this->belongsTo(EventType::class, 'id_event_type');
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
        return isset($windows[0]['date'], $windows[0]['time_from'], $windows[0]['time_to'])
            && $windows[0]['date'] && $windows[0]['time_from'] && $windows[0]['time_to'];
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

        $mandatoryChecks = [
            'budget_ratified' => (bool) $this->budget_ratified,
            'event_type' => (bool) ($this->id_event_type || $this->event_type_other),
            'event_start_datetime' => (bool) $this->event_start_datetime,
            'event_end_datetime' => (bool) $this->event_end_datetime,
            'address' => (bool) $this->address,
            'order_contact' => (bool) ($this->order_contact_name && $this->order_contact_phone),
            'delivery_windows' => $this->hasFirstWindow($this->delivery_windows),
            'pickup_windows' => $this->hasFirstWindow($this->pickup_windows),
            'reception_contact' => (bool) ($this->reception_contact_name && $this->reception_contact_phone),
            'additional_order_details' => (bool) $this->additional_order_details,
            'insurance_required' => (bool) $this->insurance_required,
            'insurance_document' => $this->insurance_required !== 'yes' || (bool) $this->insurance_document_path,
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
