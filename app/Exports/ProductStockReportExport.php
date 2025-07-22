<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithColumnWidths;

class ProductStockReportExport implements FromCollection, WithHeadings, WithColumnWidths
{
    protected $products;
    protected $dates;

    public function __construct($products, $dates)
    {
        $this->products = $products;
        $this->dates = $dates;
    }

    public function collection()
    {
        return collect($this->products)->map(function ($product) {
            $row = [
                $product['id'],
                $product['name'],
                $product['code'],
                $product['stock'] ?? '-',
            ];

            foreach ($this->dates as $date) {
                $row[] = $product['used_stock_by_day'][$date] ?? 0;
            }

            return $row;
        });
    }

    public function headings(): array
    {
        return array_merge(
            ['ID', 'Nombre', 'Código', 'Stock'],
            $this->dates->toArray()
        );
    }

    public function columnWidths(): array
    {
        // A = ID, B = Nombre, C = Código, D = Stock, E+ = Fechas
        $columns = [
            'A' => 10,
            'B' => 30,
            'C' => 15,
            'D' => 10,
        ];

        $start = ord('E');
        foreach (range(0, count($this->dates) - 1) as $i) {
            $columnLetter = chr($start + $i); // A-Z solamente (hasta 26 fechas)
            $columns[$columnLetter] = 15;
        }

        return $columns;
    }
}
