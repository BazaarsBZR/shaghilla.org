<?php

namespace App\Services\PublicMoney;

final class MoneyParser
{
    /** @return array{amount: ?string, currency: ?string, original: string} */
    public static function parse(?string $value): array
    {
        $original = trim((string) $value);
        if ($original === '' || in_array(mb_strtolower($original), ['n/a', 'na', '-', 'confidential'], true)) {
            return ['amount' => null, 'currency' => null, 'original' => $original];
        }

        $upper = mb_strtoupper($original);
        $currency = match (true) {
            str_contains($upper, 'USD') || str_contains($original, '$') => 'USD',
            str_contains($upper, 'LBP') || str_contains($upper, 'L.L') || str_contains($original, 'ل.ل') => 'LBP',
            str_contains($upper, 'EUR') || str_contains($original, '€') => 'EUR',
            default => null,
        };

        if (! preg_match('/[-+]?\d[\d\s,.]*/u', $original, $match)) {
            return ['amount' => null, 'currency' => $currency, 'original' => $original];
        }

        $number = preg_replace('/\s+/', '', $match[0]);
        if (str_contains($number, ',') && str_contains($number, '.')) {
            $number = strrpos($number, '.') > strrpos($number, ',')
                ? str_replace(',', '', $number)
                : str_replace('.', '', str_replace(',', '.', $number));
        } elseif (substr_count($number, ',') === 1 && preg_match('/,\d{1,2}$/', $number)) {
            $number = str_replace(',', '.', $number);
        } else {
            $number = str_replace(',', '', $number);
        }

        return is_numeric($number)
            ? ['amount' => number_format((float) $number, 4, '.', ''), 'currency' => $currency, 'original' => $original]
            : ['amount' => null, 'currency' => $currency, 'original' => $original];
    }
}
