<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Merchant;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'payment_code' => 'LTP-'.now()->format('Ymd').'-'.fake()->unique()->numerify('######'),
            'customer_document' => (string) fake()->numerify('########'),
            'amount_minor' => fake()->numberBetween(100, 500_000),
            'currency' => 'PEN',
            'status' => PaymentStatus::PENDING,
            'description' => fake()->sentence(),
        ];
    }
}
