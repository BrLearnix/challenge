<?php

namespace App\Models;

use App\Enums\ReconciliationMovementOutcome;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankReconciliationMovement extends Model
{
    protected $fillable = [
        'bank_reconciliation_batch_id',
        'bank_movement_id',
        'bank_transaction_id',
        'payment_code',
        'amount_minor',
        'currency',
        'paid_at',
        'outcome',
        'payment_id',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'metadata' => 'array',
            'outcome' => ReconciliationMovementOutcome::class,
        ];
    }

    /**
     * @return BelongsTo<BankReconciliationBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(BankReconciliationBatch::class, 'bank_reconciliation_batch_id');
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
