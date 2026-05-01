<?php

namespace Tests\Feature\Api\V1;

use App\Enums\BankNotificationProcessingOutcome;
use App\Enums\ExternalNotificationStatus;
use App\Enums\PaymentStatus;
use App\Models\BankNotification;
use App\Models\ExternalNotification;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankNotificationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> */
    private function bankWebhookHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.config('services.bank_webhook.secret'),
            'Accept' => 'application/json',
        ];
    }

    /** @param  array<string, mixed>  $overrides */
    private function bankPayload(array $overrides = []): array
    {
        return array_merge([
            'event_id' => 'evt_test_001',
            'bank_transaction_id' => 'bank_tx_test_999',
            'payment_code' => 'LTP-20260430-000001',
            'amount' => 150.50,
            'currency' => 'PEN',
            'status' => 'PAID',
            'paid_at' => '2026-04-24 20:44:00',
        ], $overrides);
    }

    public function test_rejects_webhook_without_valid_auth(): void
    {
        $payment = Payment::factory()->create([
            'payment_code' => 'LTP-20260430-000001',
            'amount_minor' => 15050,
            'currency' => 'PEN',
            'status' => PaymentStatus::PENDING,
        ]);

        $response = $this->postJson('/api/v1/bank/notifications', $this->bankPayload(), [
            'Authorization' => 'Bearer wrong-secret',
        ]);

        $response->assertUnauthorized();
        $payment->refresh();
        $this->assertSame(PaymentStatus::PENDING, $payment->status);
    }

    public function test_valid_notification_marks_payment_paid(): void
    {
        $payment = Payment::factory()->create([
            'payment_code' => 'LTP-20260430-000001',
            'amount_minor' => 15050,
            'currency' => 'PEN',
            'status' => PaymentStatus::PENDING,
        ]);

        $response = $this->postJson('/api/v1/bank/notifications', $this->bankPayload(), $this->bankWebhookHeaders());

        $response->assertAccepted()
            ->assertJsonPath('status', BankNotificationProcessingOutcome::QUEUED->value);

        $payment->refresh();
        $this->assertSame(PaymentStatus::PAID, $payment->status);
        $this->assertNotNull($payment->paid_at);

        $notification = BankNotification::query()->firstOrFail();
        $this->assertSame(BankNotificationProcessingOutcome::PROCESSED_PAID->value, $notification->processing_outcome);
        $this->assertSame($payment->id, $notification->payment_id);
        $this->assertNotNull($notification->processed_at);

        $external = ExternalNotification::query()->where('payment_id', $payment->id)->firstOrFail();
        $this->assertSame(ExternalNotificationStatus::SENT, $external->status);
        $this->assertGreaterThanOrEqual(1, $external->attempt_count);
    }

    public function test_duplicate_event_id_is_idempotent(): void
    {
        Payment::factory()->create([
            'payment_code' => 'LTP-20260430-000001',
            'amount_minor' => 15050,
            'currency' => 'PEN',
            'status' => PaymentStatus::PENDING,
        ]);

        $payload = $this->bankPayload();

        $this->postJson('/api/v1/bank/notifications', $payload, $this->bankWebhookHeaders())->assertAccepted();

        $response = $this->postJson('/api/v1/bank/notifications', $payload, $this->bankWebhookHeaders());

        $response->assertOk()
            ->assertJsonPath('duplicate_event_id', true);

        $this->assertSame(1, BankNotification::query()->count());
    }

    public function test_duplicate_bank_transaction_id_is_rejected(): void
    {
        Payment::factory()->create([
            'payment_code' => 'LTP-20260430-000001',
            'amount_minor' => 15050,
            'currency' => 'PEN',
            'status' => PaymentStatus::PENDING,
        ]);

        $first = $this->bankPayload([
            'event_id' => 'evt_a',
            'bank_transaction_id' => 'same_txn',
        ]);

        $this->postJson('/api/v1/bank/notifications', $first, $this->bankWebhookHeaders())->assertAccepted();

        $second = $this->bankPayload([
            'event_id' => 'evt_b',
            'bank_transaction_id' => 'same_txn',
            'payment_code' => 'LTP-20260430-000002',
        ]);

        Payment::factory()->create([
            'payment_code' => 'LTP-20260430-000002',
            'amount_minor' => 15050,
            'currency' => 'PEN',
            'status' => PaymentStatus::PENDING,
        ]);

        $response = $this->postJson('/api/v1/bank/notifications', $second, $this->bankWebhookHeaders());

        $response->assertOk()
            ->assertJsonPath('duplicate_bank_transaction_id', true);

        $this->assertSame(1, BankNotification::query()->where('bank_transaction_id', 'same_txn')->count());
    }

    public function test_wrong_amount_marks_observed(): void
    {
        Payment::factory()->create([
            'payment_code' => 'LTP-20260430-000001',
            'amount_minor' => 15050,
            'currency' => 'PEN',
            'status' => PaymentStatus::PENDING,
        ]);

        $this->postJson('/api/v1/bank/notifications', $this->bankPayload([
            'amount' => 200.00,
        ]), $this->bankWebhookHeaders())->assertAccepted();

        $payment = Payment::query()->where('payment_code', 'LTP-20260430-000001')->firstOrFail();
        $this->assertSame(PaymentStatus::OBSERVED, $payment->status);

        $notification = BankNotification::query()->firstOrFail();
        $this->assertSame(BankNotificationProcessingOutcome::PROCESSED_OBSERVED->value, $notification->processing_outcome);
    }

    public function test_hmac_signature_header_is_accepted(): void
    {
        Payment::factory()->create([
            'payment_code' => 'LTP-20260430-000001',
            'amount_minor' => 15050,
            'currency' => 'PEN',
            'status' => PaymentStatus::PENDING,
        ]);

        $body = json_encode($this->bankPayload(), JSON_THROW_ON_ERROR);
        $secret = config('services.bank_webhook.secret');
        $this->assertIsString($secret);
        $signature = hash_hmac('sha256', $body, $secret);

        $response = $this->call('POST', '/api/v1/bank/notifications', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_BANK_SIGNATURE' => $signature,
        ], $body);

        $response->assertAccepted();

        $paid = Payment::query()->firstOrFail();
        $this->assertSame(PaymentStatus::PAID, $paid->status);

        $external = ExternalNotification::query()->where('payment_id', $paid->id)->firstOrFail();
        $this->assertSame(ExternalNotificationStatus::SENT, $external->status);
    }
}
