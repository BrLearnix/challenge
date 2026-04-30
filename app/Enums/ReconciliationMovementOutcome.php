<?php

namespace App\Enums;

enum ReconciliationMovementOutcome: string
{
    case PENDING = 'PENDING';
    case MATCHED = 'MATCHED';
    case DISCREPANCY = 'DISCREPANCY';
    case UNMATCHED = 'UNMATCHED';
    case DUPLICATE_BATCH = 'DUPLICATE_BATCH';
}
