<?php

namespace App\Support;

final class Money
{
    /**
     * Convert a major-unit amount (e.g. PEN with 2 decimals) to minor units (centavos).
     */
    public static function toMinorUnits(float|string $amount): int
    {
        $normalized = number_format((float) $amount, 2, '.', '');

        return (int) bcmul($normalized, '100', 0);
    }

    public static function minorToDecimalString(int $minorUnits): string
    {
        return number_format($minorUnits / 100, 2, '.', '');
    }
}
