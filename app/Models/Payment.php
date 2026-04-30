<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'payment_code',
        'customer_document',
        'amount_minor',
        'currency',
        'status',
        'description',
        'paid_at',
        'observed_reason',
        'reconciliation_match',
        'settled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'paid_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * @return HasMany<BankNotification, $this>
     */
    public function bankNotifications(): HasMany
    {
        return $this->hasMany(BankNotification::class);
    }

    /**
     * @return HasMany<BankReconciliationMovement, $this>
     */
    public function reconciliationMovements(): HasMany
    {
        return $this->hasMany(BankReconciliationMovement::class);
    }

    /**
     * @return HasMany<PaymentAudit, $this>
     */
    public function audits(): HasMany
    {
        return $this->hasMany(PaymentAudit::class);
    }

    /**
     * @return HasOne<ExternalNotification, $this>
     */
    public function externalNotification(): HasOne
    {
        return $this->hasOne(ExternalNotification::class);
    }
}
