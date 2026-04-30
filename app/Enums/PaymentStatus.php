<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case PENDING = 'PENDING';
    case PAID = 'PAID';
    case OBSERVED = 'OBSERVED';
    case RECONCILED = 'RECONCILED';
    case SETTLEMENT_PENDING = 'SETTLEMENT_PENDING';
    case SETTLED = 'SETTLED';
}
