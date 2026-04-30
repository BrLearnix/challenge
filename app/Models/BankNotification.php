<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankNotification extends Model
{
    protected $fillable = [
        'event_id',
        'bank_transaction_id',
        'payment_id',
        'payment_code',
        'payload',
        'amount_minor',
        'currency',
        'reported_payment_status',
        'paid_at',
        'processing_outcome',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'paid_at' => 'datetime',
            'processed_at' => 'datetime',
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
