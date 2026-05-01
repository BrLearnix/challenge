<?php

namespace App\Http\Resources;

use App\Support\Money;
use App\Support\SettlementCandidateRow;
use App\Support\SettlementEligibilityCalculator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SettlementCandidateRow
 */
class SettlementCandidateResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SettlementCandidateRow $row */
        $row = $this->resource;
        $payment = $row->payment;
        $tz = SettlementEligibilityCalculator::TIMEZONE;

        return [
            'payment_code' => $payment->payment_code,
            'status' => $payment->status->value,
            'amount' => Money::minorToDecimalString((int) $payment->amount_minor),
            'currency' => $payment->currency,
            'merchant_id' => $payment->merchant_id,
            'paid_at' => $payment->paid_at?->copy()->timezone($tz)->toIso8601String(),
            'settlement_eligible_from' => Carbon::parse($row->settlementEligibleFrom)->timezone($tz)->toDateString(),
        ];
    }
}
