<?php

namespace Tests\Feature\Api\V1;

use App\Models\Merchant;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreatePaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_pending_payment_with_expected_shape(): void
    {
        $merchant = Merchant::factory()->create();

        $response = $this->postJson('/api/v1/payments', [
            'merchant_id' => $merchant->id,
            'customer_document' => '76359665',
            'amount' => 150.50,
            'currency' => 'PEN',
            'description' => 'Pago de servicio mensual',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'payment_code', 'status', 'amount', 'currency',
            ])
            ->assertJsonPath('status', 'PENDING')
            ->assertJsonPath('amount', '150.50')
            ->assertJsonPath('currency', 'PEN');

        $code = $response->json('payment_code');
        $this->assertMatchesRegularExpression('/^LTP-\d{8}-\d{6}$/', $code);

        $this->assertDatabaseHas('payments', [
            'merchant_id' => $merchant->id,
            'payment_code' => $code,
            'customer_document' => '76359665',
            'amount_minor' => 15050,
            'currency' => 'PEN',
            'status' => 'PENDING',
        ]);
    }

    public function test_rejects_invalid_merchant(): void
    {
        $response = $this->postJson('/api/v1/payments', [
            'merchant_id' => 999999,
            'customer_document' => '76359665',
            'amount' => 10,
            'currency' => 'PEN',
        ]);

        $response->assertUnprocessable();
    }

    public function test_rejects_more_than_two_decimal_places(): void
    {
        $merchant = Merchant::factory()->create();

        $response = $this->postJson('/api/v1/payments', [
            'merchant_id' => $merchant->id,
            'customer_document' => '76359665',
            'amount' => 10.001,
            'currency' => 'PEN',
        ]);

        $response->assertUnprocessable();
    }

    public function test_increments_daily_sequence(): void
    {
        $merchant = Merchant::factory()->create();

        $this->postJson('/api/v1/payments', [
            'merchant_id' => $merchant->id,
            'customer_document' => '111',
            'amount' => 1,
            'currency' => 'PEN',
        ])->assertCreated();

        $this->postJson('/api/v1/payments', [
            'merchant_id' => $merchant->id,
            'customer_document' => '222',
            'amount' => 2,
            'currency' => 'PEN',
        ])->assertCreated();

        $codes = Payment::query()->orderBy('id')->pluck('payment_code');
        $suffixes = $codes->map(fn (string $c) => (int) substr($c, -6));

        $this->assertSame([1, 2], $suffixes->all());
    }
}
