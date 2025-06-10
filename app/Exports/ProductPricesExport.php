<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithColumnWidths; // <-- agregar esto

class ProductPricesExport implements FromCollection, WithHeadings, WithColumnWidths
{
    protected $prices;

    public function __construct($prices)
    {
        $this->prices = $prices;
    }

    public function collection()
    {
        return collect($this->prices);
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15,  // ID Producto
            'B' => 30,  // Nombre
            'C' => 20,  // Código
            'D' => 15,  // Precio Vigente
        ];
    }

    public function headings(): array
    {
        return [
            'ID Producto',
            'Nombre',
            'Código',
            'Precio Vigente',
        ];
    }
}
