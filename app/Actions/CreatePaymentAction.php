<?php

namespace App\Actions;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\PaymentCodeGenerator;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class CreatePaymentAction
{
    public function __construct(
        private readonly PaymentCodeGenerator $paymentCodeGenerator,
    ) {}

    /**
     * @param  array{merchant_id: int, customer_document: string, amount: float|string, currency: string, description?: string|null}  $data
     */
    public function execute(array $data): Payment
    {
        $currency = strtoupper($data['currency']);

        return DB::transaction(function () use ($data, $currency): Payment {
            return Payment::query()->create([
                'merchant_id' => $data['merchant_id'],
                'payment_code' => $this->paymentCodeGenerator->generate(),
                'customer_document' => $data['customer_document'],
                'amount_minor' => Money::toMinorUnits($data['amount']),
                'currency' => $currency,
                'status' => PaymentStatus::PENDING,
                'description' => $data['description'] ?? null,
            ]);
        });
    }
}
