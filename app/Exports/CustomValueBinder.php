<?php
namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\IValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class CustomValueBinder extends DefaultValueBinder implements IValueBinder
{
    public function bindValue(Cell $cell, $value)
    {
        // Forzar que los valores 0 sean tratados como números
        if ($value === 0 || $value === '0') {
            $cell->setValueExplicit($value, DataType::TYPE_NUMERIC);
            return true;
        }

        // Usar comportamiento por defecto para el resto
        return parent::bindValue($cell, $value);
    }
}
