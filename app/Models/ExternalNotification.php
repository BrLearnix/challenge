<?php

namespace App\Models;

use App\Enums\ExternalNotificationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalNotification extends Model
{
    protected $fillable = [
        'payment_id',
        'status',
        'attempt_count',
        'last_attempt_at',
        'last_error',
        'payload_snapshot',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ExternalNotificationStatus::class,
            'last_attempt_at' => 'datetime',
            'payload_snapshot' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
