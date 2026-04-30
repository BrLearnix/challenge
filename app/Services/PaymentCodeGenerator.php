<?php

namespace App\Services;

use App\Models\PaymentSequence;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class PaymentCodeGenerator
{
    /**
     * Payment code: LTP-{YYYYMMDD}-{000001} using app timezone (America/Lima).
     * Run inside the caller's DB transaction (see CreatePaymentAction) so SQLite + RefreshDatabase see a consistent snapshot.
     */
    public function generate(): string
    {
        $date = Carbon::now(config('app.timezone'))->toDateString();
        $compact = str_replace('-', '', $date);
        $serial = $this->allocateSerial($date);

        return sprintf('LTP-%s-%06d', $compact, $serial);
    }

    private function allocateSerial(string $date): int
    {
        // Use whereDate so SQLite/MySQL match rows consistently (stored value may include time).
        $seq = $this->sequenceQueryForDate($date)->first();

        if ($seq === null) {
            try {
                PaymentSequence::query()->create([
                    'sequence_date' => $date,
                    'last_serial' => 1,
                ]);

                return 1;
            } catch (QueryException $e) {
                if (! $this->isDuplicateKey($e)) {
                    throw $e;
                }

                $seq = $this->sequenceQueryForDate($date)->first();

                if ($seq === null) {
                    throw $e;
                }

                return $this->incrementSerial($seq);
            }
        }

        return $this->incrementSerial($seq);
    }

    private function incrementSerial(PaymentSequence $seq): int
    {
        $next = $seq->last_serial + 1;
        $seq->update(['last_serial' => $next]);

        return $next;
    }

    private function isDuplicateKey(QueryException $e): bool
    {
        $driverCode = $e->errorInfo[1] ?? null;

        return $driverCode === 1062 || $driverCode === 19;
    }

    /**
     * @return Builder<PaymentSequence>
     */
    private function sequenceQueryForDate(string $date)
    {
        $query = PaymentSequence::query()->whereDate('sequence_date', $date);

        if (DB::connection()->getDriverName() === 'mysql') {
            $query->lockForUpdate();
        }

        return $query;
    }
}
