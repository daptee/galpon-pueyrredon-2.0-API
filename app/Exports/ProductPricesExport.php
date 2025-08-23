<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithColumnWidths;

class ProductPricesExport implements FromCollection, WithHeadings, WithMapping, WithColumnWidths
{
    protected $prices;

    // Orden de columnas fijo
    protected $columns = [
        'id_product',
        'name',
        'code',
        'volume',
        'line',
        'type',
        'furniture',
        'vigente_price',
        'color',
        'diametro',
        'altura',
        'volumen_atributo',
        'componentes',
        'estado',
    ];

    public function __construct($prices)
    {
        $this->prices = $prices;
    }

    public function collection()
    {
        return collect($this->prices);
    }

    public function map($row): array
    {
        $mapped = [];

        foreach ($this->columns as $col) {
            $mapped[] = $row[$col] ?? null;
        }

        return $mapped;
    }

    public function headings(): array
    {
        return [
            'ID Producto',
            'Nombre',
            'Código',
            'Volumen',
            'Linea',
            'Tipo',
            'Mueble',
            'Precio Vigente',
            'Color',
            'Diametro',
            'Altura',
            'Volumen',
            'Componentes',
            'Estado',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15,  // ID Producto
            'B' => 30,  // Nombre
            'C' => 20,  // Código
            'D' => 15,  // Volumen
            'E' => 20,  // Línea
            'F' => 20,  // Tipo
            'G' => 20,  // Mueble
            'H' => 15,  // Precio Vigente
            'I' => 20,  // Color
            'J' => 15,  // Diametro
            'K' => 15,  // Altura
            'L' => 15,  // Volumen (atributo)
            'M' => 50,  // Componentes
            'N' => 15,  // Estado
        ];
    }
}
