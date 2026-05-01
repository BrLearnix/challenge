<?php

namespace App\Support;

use App\Models\Payment;
use Carbon\CarbonInterface;

readonly class SettlementCandidateRow
{
    public function __construct(
        public Payment $payment,
        public CarbonInterface $settlementEligibleFrom,
    ) {}
}
