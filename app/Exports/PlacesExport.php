<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithColumnWidths; // <-- agregar esto

class PlacesExport implements FromCollection, WithHeadings, WithColumnWidths
{
    protected $places;

    public function __construct($places)
    {
        $this->places = $places;
    }

    public function collection()
    {
        return collect($this->places);
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15,  
            'B' => 30,  
            'C' => 20,  
            'D' => 15,  
            'E' => 50,  
            'F' => 20,  
            'G' => 20,  
            'H' => 70,  
            'I' => 20,
            'J' => 20,
            'K' => 20,
            'L' => 20,
            'M' => 30,
            'N' => 20,
        ];
    }

    public function headings(): array
    {
        return [
            'ID Lugar',
            'Nombre',
            'Distacia',
            'Tiempo de Viaje',
            'Dirección',
            'Teléfono',
            'Factor de Complejidad',
            'Observaciones',
            'Provincia',
            'Localidad',
            'Tipo de Recolección',
            'Área',
            'Nombre del Peaje',
            'Costo del Peaje',
        ];
    }
}
