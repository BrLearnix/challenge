<?php

namespace App\Enums;

enum ExternalNotificationStatus: string
{
    case PENDING = 'PENDING';
    case SENT = 'SENT';
    case FAILED = 'FAILED';
}
