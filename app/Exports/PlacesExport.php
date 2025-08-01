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
        ];
    }

    public function headings(): array
    {
       /*  'id_place' => $place->id,
                    'name' => $place->name,
                    'distance' => $place->distance,
                    'travel_time' => $place->travel_time,
                    'address' => $place->address,
                    'phone' => $place->phone,
                    'complexity_factor' => $place->complexity_factor,
                    'observations' => $place->observations,
                    'province' => $place->province->province ?? null,
                    'locality' => $place->locality->locality ?? null,
                    'collection_type' => $place->place_collection_type->name ?? null,
                    'area' => $place->place_area->name ?? null, */
        return [
            'ID Producto',
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
        ];
    }
}
