<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankReconciliationBatch extends Model
{
    protected $fillable = [
        'bank',
        'process_date',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'process_date' => 'date',
        ];
    }

    /**
     * @return HasMany<BankReconciliationMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(BankReconciliationMovement::class);
    }
}
