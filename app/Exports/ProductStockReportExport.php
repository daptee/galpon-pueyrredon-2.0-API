<?php

namespace App\Exports;

use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use App\Exports\CustomValueBinder;

class ProductStockReportExport extends CustomValueBinder implements FromCollection, WithHeadings, WithColumnWidths, WithMapping, WithCustomValueBinder
{
    protected $products;
    protected $dates;

    public function __construct($products, $dates)
    {
        $this->products = $products;
        $this->dates = is_array($dates) ? $dates : $dates->toArray();
    }

    public function collection()
    {
        return collect($this->products);
    }

    public function map($product): array
    {
        $row = [
            $product['id'],
            $product['name'],
            $product['code'],
            $product['stock'] ?? "-",
            $product['show_catalog'] ?? 'No',
        ];

        foreach ($this->dates as $date) {
            $rawValue = $product['used_stock_by_day'][$date] ?? null;
            $value = (is_numeric($rawValue) && $rawValue !== null) ? (string) (int) $rawValue : '0';
            $row[] = $value;
        }

        return $row;
    }

    public function headings(): array
    {
        return array_merge(
            ['ID', 'Nombre', 'Código', 'Stock', 'Catálogo'],
            $this->dates
        );
    }

    public function columnWidths(): array
    {
        $columns = [
            'A' => 10,
            'B' => 30,
            'C' => 15,
            'D' => 10,
            'E' => 12,
        ];

        $start = ord('F');
        foreach (range(0, count($this->dates) - 1) as $i) {
            $columnLetter = chr($start + $i);
            $columns[$columnLetter] = 15;
        }

        return $columns;
    }
}
