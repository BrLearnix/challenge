<?php

namespace App\Http\Resources;

use App\Models\Payment;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /** @var string|null Keep payload flat per API spec (not wrapped in "data"). */
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'payment_code' => $this->payment_code,
            'status' => $this->status->value,
            'amount' => Money::minorToDecimalString((int) $this->amount_minor),
            'currency' => $this->currency,
        ];
    }
}
