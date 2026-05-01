<?php

namespace App\Actions;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Support\SettlementCandidateRow;
use App\Support\SettlementEligibilityCalculator;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class ListSettlementCandidatesAction
{
    public function __construct(
        private SettlementEligibilityCalculator $calculator,
    ) {}

    /**
     * @return Collection<int, SettlementCandidateRow>
     */
    public function execute(CarbonInterface $asOfDateStartLima, ?int $merchantId = null): Collection
    {
        $asOf = Carbon::parse($asOfDateStartLima)->timezone(SettlementEligibilityCalculator::TIMEZONE)->startOfDay();

        $query = Payment::query()
            ->whereIn('status', [PaymentStatus::PAID, PaymentStatus::RECONCILED])
            ->whereNotNull('paid_at')
            ->whereNull('settled_at')
            ->where(function ($q): void {
                $q->whereNull('reconciliation_match')
                    ->orWhere('reconciliation_match', '!=', 'DISCREPANCY');
            })
            ->orderBy('paid_at');

        if ($merchantId !== null) {
            $query->where('merchant_id', $merchantId);
        }

        $tz = SettlementEligibilityCalculator::TIMEZONE;

        return $query->get()->map(function (Payment $payment) use ($tz): ?SettlementCandidateRow {
            $paidAt = $payment->paid_at;
            if ($paidAt === null) {
                return null;
            }

            $eligibleFrom = Carbon::parse($this->calculator->firstSettlementEligibleDay($paidAt->timezone($tz)))
                ->timezone($tz)
                ->startOfDay();

            return new SettlementCandidateRow($payment, $eligibleFrom);
        })->filter()->filter(fn (SettlementCandidateRow $row) => $row->settlementEligibleFrom->lte($asOf))->values();
    }
}
