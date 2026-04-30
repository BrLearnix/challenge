<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentSequence extends Model
{
    protected $fillable = [
        'sequence_date',
        'last_serial',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence_date' => 'date',
        ];
    }
}
