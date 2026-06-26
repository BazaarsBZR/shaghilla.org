<?php

namespace App\Support\Filament;

class OptionalFilamentActions
{
    public static function exportAction(): mixed
    {
        $exportActionClass = 'pxlrbt\\FilamentExcel\\Actions\\Tables\\ExportAction';

        if (! class_exists($exportActionClass)) {
            return null;
        }

        return $exportActionClass::make()->label('Export');
    }
}
