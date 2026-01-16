<?php

namespace App\Exports;

use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithColumnWidths;

class BudgetsExport implements FromCollection, WithHeadings, WithColumnWidths
{
    protected $budgets;

    public function __construct($budgets)
    {
        $this->budgets = $budgets;
    }

    public function collection()
    {
        return collect($this->budgets)->map(function ($budget) {
            $client = $budget['client'] ?? null;
            $place = $budget['place'] ?? null;

            $clientName = $client
                ? ($client['company'] ?? ($client['name'] . ' ' . ($client['lastname'] ?? '')))
                : ($budget['client_name'] ?? 'Sin cliente');

            return [
                $clientName,
                $place['name'] ?? 'Sin lugar',
                $budget['date_event'],
                $budget['days'],
                $budget['quoted_days'],
                $budget['total_price_products'],
                $budget['total'],
                $budget['transportation_cost_edited'] ?? $budget['transportation_cost'],
                $this->getTotalPagado($budget['payments']),
                $budget['iva'],
                $budget['volume'],
                $budget['client_bonification_edited'] ?? $budget['client_bonification'],
                $place['distance'] ?? 0,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Cliente',
            'Lugar',
            'Fecha',
            'Cantidad de Jornadas',
            'Jornadas cobradas',
            'Monto mobiliario ($)',
            'Total ($)',
            'Monto traslado ($)',
            'Monto cobrado ($)',
            'Impuesto ($)',
            'Volumen mobiliario (m^3)',
            'Bonificación ($)',
            'Distancia (Km)',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 25,
            'B' => 25,
            'C' => 15,
            'D' => 20,
            'E' => 18,
            'F' => 18,
            'G' => 18,
            'H' => 18,
            'I' => 18,
            'J' => 15,
            'K' => 25,
            'L' => 18,
            'M' => 15,
        ];
    }

    private function getTotalPagado($payments)
    {

        if (!$payments || $payments->isEmpty()) return 0;

        return collect($payments)->sum(function ($payment) {
            return $payment['amount'];
        });
    }
}

