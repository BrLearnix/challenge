<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Reglas del enunciado (Módulo F): hora de corte 20:45 en {@see self::TIMEZONE}.
 *
 * - Confirmación hasta las 20:45 (inclusive): liquidación desde el siguiente día hábil.
 * - Confirmación después de 20:45: desde el subsiguiente día hábil (saltar un día hábil más).
 *
 * Día hábil: solo excluye sábado y domingo (feriados locales no están modelados; ver README).
 */
final class SettlementEligibilityCalculator
{
    public const TIMEZONE = 'America/Lima';

    private const CUTOFF_HOUR = 20;

    private const CUTOFF_MINUTE = 45;

    /**
     * Primer día calendario (inicio del día en Lima) a partir del cual el pago es elegible para liquidación.
     */
    public function firstSettlementEligibleDay(CarbonInterface $paidAt): CarbonInterface
    {
        $paidAt = Carbon::parse($paidAt)->timezone(self::TIMEZONE);

        $paidCalendarStart = $paidAt->copy()->startOfDay();
        $cutoff = $paidCalendarStart->copy()->setTime(self::CUTOFF_HOUR, self::CUTOFF_MINUTE, 0);

        if ($paidAt->lte($cutoff)) {
            return $this->nextBusinessDayAfter($paidCalendarStart);
        }

        $afterFirstShift = $this->nextBusinessDayAfter($paidCalendarStart);

        return $this->nextBusinessDayAfter($afterFirstShift);
    }

    /**
     * Primer día hábil estrictamente posterior al día calendario indicado (00:00 Lima).
     */
    public function nextBusinessDayAfter(CarbonInterface $dayStartLima): Carbon
    {
        $d = Carbon::parse($dayStartLima)->timezone(self::TIMEZONE)->startOfDay()->addDay();

        while ($d->isWeekend()) {
            $d->addDay();
        }

        return $d;
    }
}
