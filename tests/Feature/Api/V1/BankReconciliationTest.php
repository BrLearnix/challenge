<?php

namespace Tests\Feature\Api\V1;

use App\Enums\BankNotificationProcessingOutcome;
use App\Enums\ExternalNotificationStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReconciliationMovementOutcome;
use App\Models\BankNotification;
use App\Models\BankReconciliationMovement;
use App\Models\ExternalNotification;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankReconciliationTest extends TestCase
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
    private function reconciliationPayload(array $overrides = []): array
    {
        return array_merge([
            'bank' => 'BANK_A',
            'process_date' => '2026-04-24',
            'movements' => [
                [
                    'bank_movement_id' => 'mov_001',
                    'bank_transaction_id' => 'bank_tx_999',
                    'payment_code' => 'LTP-20260424-000001',
                    'amount' => 150.50,
                    'currency' => 'PEN',
                    'paid_at' => '2026-04-24 20:44:30',
                ],
            ],
        ], $overrides);
    }

    private function seedRealtimePaidPayment(): Payment
    {
        $payment = Payment::factory()->create([
            'payment_code' => 'LTP-20260424-000001',
            'amount_minor' => 15050,
            'currency' => 'PEN',
            'status' => PaymentStatus::PAID,
            'paid_at' => '2026-04-24 20:44:00',
        ]);

        BankNotification::query()->create([
            'event_id' => 'evt_rec_001',
            'bank_transaction_id' => 'bank_tx_999',
            'payment_id' => $payment->id,
            'payment_code' => $payment->payment_code,
            'payload' => [],
            'amount_minor' => 15050,
            'currency' => 'PEN',
            'reported_payment_status' => 'PAID',
            'paid_at' => $payment->paid_at,
            'processing_outcome' => BankNotificationProcessingOutcome::PROCESSED_PAID->value,
            'processed_at' => now(),
        ]);

        return $payment;
    }

    public function test_matches_movement_with_realtime_confirmation(): void
    {
        $payment = $this->seedRealtimePaidPayment();

        $response = $this->postJson(
            '/api/v1/bank/reconciliation',
            $this->reconciliationPayload(),
            $this->bankWebhookHeaders(),
        );

        $response->assertOk()
            ->assertJsonPath('summary.matched', 1)
            ->assertJsonPath('summary.discrepancy', 0)
            ->assertJsonPath('summary.unmatched', 0);

        $payment->refresh();
        $this->assertSame(PaymentStatus::RECONCILED, $payment->status);
        $this->assertSame('MATCHED', $payment->reconciliation_match);

        $movement = BankReconciliationMovement::query()->firstOrFail();
        $this->assertSame(ReconciliationMovementOutcome::MATCHED, $movement->outcome);
    }

    public function test_detects_amount_discrepancy_vs_realtime_payment(): void
    {
        $payment = $this->seedRealtimePaidPayment();

        $response = $this->postJson(
            '/api/v1/bank/reconciliation',
            $this->reconciliationPayload([
                'movements' => [
                    [
                        'bank_movement_id' => 'mov_001',
                        'bank_transaction_id' => 'bank_tx_999',
                        'payment_code' => 'LTP-20260424-000001',
                        'amount' => 200.00,
                        'currency' => 'PEN',
                        'paid_at' => '2026-04-24 20:44:30',
                    ],
                ],
            ]),
            $this->bankWebhookHeaders(),
        );

        $response->assertOk()
            ->assertJsonPath('summary.discrepancy', 1)
            ->assertJsonPath('summary.matched', 0);

        $payment->refresh();
        $this->assertSame(PaymentStatus::PAID, $payment->status);
        $this->assertSame('DISCREPANCY', $payment->reconciliation_match);
    }

    public function test_unmatched_when_payment_code_unknown(): void
    {
        $this->seedRealtimePaidPayment();

        $response = $this->postJson(
            '/api/v1/bank/reconciliation',
            $this->reconciliationPayload([
                'movements' => [
                    [
                        'bank_movement_id' => 'mov_001',
                        'bank_transaction_id' => 'bank_tx_x',
                        'payment_code' => 'LTP-NO-EXISTE',
                        'amount' => 150.50,
                        'currency' => 'PEN',
                        'paid_at' => '2026-04-24 20:44:30',
                    ],
                ],
            ]),
            $this->bankWebhookHeaders(),
        );

        $response->assertOk()
            ->assertJsonPath('summary.unmatched', 1);

        $this->assertSame(ReconciliationMovementOutcome::UNMATCHED, BankReconciliationMovement::query()->first()->outcome);
    }

    public function test_skips_duplicate_bank_movement_id_in_same_batch(): void
    {
        $this->seedRealtimePaidPayment();

        $payload = $this->reconciliationPayload([
            'movements' => [
                [
                    'bank_movement_id' => 'mov_dup',
                    'bank_transaction_id' => 'bank_tx_999',
                    'payment_code' => 'LTP-20260424-000001',
                    'amount' => 150.50,
                    'currency' => 'PEN',
                    'paid_at' => '2026-04-24 20:44:30',
                ],
                [
                    'bank_movement_id' => 'mov_dup',
                    'bank_transaction_id' => 'bank_tx_999',
                    'payment_code' => 'LTP-20260424-000001',
                    'amount' => 150.50,
                    'currency' => 'PEN',
                    'paid_at' => '2026-04-24 20:44:30',
                ],
            ],
        ]);

        $response = $this->postJson('/api/v1/bank/reconciliation', $payload, $this->bankWebhookHeaders());

        $response->assertOk()
            ->assertJsonPath('summary.duplicates_skipped', 1)
            ->assertJsonPath('summary.matched', 1);

        $this->assertSame(1, BankReconciliationMovement::query()->where('bank_movement_id', 'mov_dup')->count());
    }

    public function test_late_confirmation_when_payment_still_pending(): void
    {
        $payment = Payment::factory()->create([
            'payment_code' => 'LTP-20260424-000001',
            'amount_minor' => 15050,
            'currency' => 'PEN',
            'status' => PaymentStatus::PENDING,
        ]);

        $response = $this->postJson(
            '/api/v1/bank/reconciliation',
            $this->reconciliationPayload([
                'movements' => [
                    [
                        'bank_movement_id' => 'mov_late',
                        'bank_transaction_id' => 'bank_tx_late',
                        'payment_code' => 'LTP-20260424-000001',
                        'amount' => 150.50,
                        'currency' => 'PEN',
                        'paid_at' => '2026-04-24 21:00:00',
                    ],
                ],
            ]),
            $this->bankWebhookHeaders(),
        );

        $response->assertOk()->assertJsonPath('summary.matched', 1);

        $payment->refresh();
        $this->assertSame(PaymentStatus::RECONCILED, $payment->status);
        $this->assertSame('MATCHED', $payment->reconciliation_match);
        $this->assertNotNull($payment->paid_at);

        $external = ExternalNotification::query()->where('payment_id', $payment->id)->firstOrFail();
        $this->assertSame(ExternalNotificationStatus::SENT, $external->status);
    }

    public function test_requires_webhook_auth(): void
    {
        $this->seedRealtimePaidPayment();

        $this->postJson('/api/v1/bank/reconciliation', $this->reconciliationPayload(), [
            'Authorization' => 'Bearer wrong',
        ])->assertUnauthorized();
    }
}
