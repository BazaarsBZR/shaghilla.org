<?php

namespace App\Support\Filament;

class OptionalFilamentActions
{
    public static function exportAction(): mixed
    {
        $exportActionClass = 'pxlrbt\\FilamentExcel\\Actions\\Pages\\ExportAction';

        if (! class_exists($exportActionClass)) {
            return null;
        }

        return $exportActionClass::make()->label('Export');
    }
}
