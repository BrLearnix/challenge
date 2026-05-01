<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PaymentStatus;
use App\Models\Merchant;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettlementCandidatesTest extends TestCase
{
    use RefreshDatabase;

    private function paidPayment(array $overrides = []): Payment
    {
        return Payment::factory()->create(array_merge([
            'status' => PaymentStatus::PAID,
            'paid_at' => Carbon::parse('2026-04-24 18:00:00', 'America/Lima'),
            'amount_minor' => 15050,
            'currency' => 'PEN',
            'reconciliation_match' => null,
            'settled_at' => null,
        ], $overrides));
    }

    public function test_lists_friday_before_cutoff_when_as_of_is_next_monday(): void
    {
        $this->paidPayment([
            'payment_code' => 'LTP-20260424-000099',
            'paid_at' => Carbon::parse('2026-04-24 18:00:00', 'America/Lima'),
        ]);

        $response = $this->getJson('/api/v1/settlements/candidates?as_of=2026-04-27');

        $response->assertOk()
            ->assertJsonPath('as_of', '2026-04-27')
            ->assertJsonPath('timezone', 'America/Lima')
            ->assertJsonPath('cutoff_time', '20:45')
            ->assertJsonCount(1, 'candidates')
            ->assertJsonPath('candidates.0.settlement_eligible_from', '2026-04-27');
    }

    public function test_excludes_friday_after_cutoff_until_subsequent_business_day(): void
    {
        $this->paidPayment([
            'payment_code' => 'LTP-20260424-000098',
            'paid_at' => Carbon::parse('2026-04-24 21:00:00', 'America/Lima'),
        ]);

        $this->getJson('/api/v1/settlements/candidates?as_of=2026-04-27')
            ->assertOk()
            ->assertJsonCount(0, 'candidates');

        $this->getJson('/api/v1/settlements/candidates?as_of=2026-04-28')
            ->assertOk()
            ->assertJsonCount(1, 'candidates')
            ->assertJsonPath('candidates.0.settlement_eligible_from', '2026-04-28');
    }

    public function test_excludes_observed_pending_and_settled(): void
    {
        $this->paidPayment(['status' => PaymentStatus::OBSERVED, 'payment_code' => 'LTP-OBS-001']);
        $this->paidPayment(['status' => PaymentStatus::PENDING, 'paid_at' => null, 'payment_code' => 'LTP-PEN-001']);
        $this->paidPayment([
            'payment_code' => 'LTP-SET-001',
            'settled_at' => Carbon::parse('2026-04-25 10:00:00', 'America/Lima'),
        ]);

        $this->getJson('/api/v1/settlements/candidates?as_of=2026-05-01')
            ->assertOk()
            ->assertJsonCount(0, 'candidates');
    }

    public function test_excludes_discrepancy_reconciliation_flag(): void
    {
        $this->paidPayment([
            'payment_code' => 'LTP-DISC-001',
            'reconciliation_match' => 'DISCREPANCY',
        ]);

        $this->getJson('/api/v1/settlements/candidates?as_of=2026-05-01')
            ->assertOk()
            ->assertJsonCount(0, 'candidates');
    }

    public function test_includes_reconciled_without_discrepancy(): void
    {
        $this->paidPayment([
            'payment_code' => 'LTP-REC-001',
            'status' => PaymentStatus::RECONCILED,
            'reconciliation_match' => 'MATCHED',
            'paid_at' => Carbon::parse('2026-04-24 18:00:00', 'America/Lima'),
        ]);

        $this->getJson('/api/v1/settlements/candidates?as_of=2026-04-27')
            ->assertOk()
            ->assertJsonCount(1, 'candidates')
            ->assertJsonPath('candidates.0.status', 'RECONCILED');
    }

    public function test_filters_by_merchant_id(): void
    {
        $mA = Merchant::factory()->create();
        $mB = Merchant::factory()->create();

        $this->paidPayment([
            'merchant_id' => $mA->id,
            'payment_code' => 'LTP-M-A-001',
        ]);
        $this->paidPayment([
            'merchant_id' => $mB->id,
            'payment_code' => 'LTP-M-B-001',
        ]);

        $response = $this->getJson('/api/v1/settlements/candidates?as_of=2026-04-27&merchant_id='.$mA->id);

        $response->assertOk()
            ->assertJsonCount(1, 'candidates')
            ->assertJsonPath('candidates.0.payment_code', 'LTP-M-A-001');
    }

    public function test_validation_error_for_invalid_as_of(): void
    {
        $this->getJson('/api/v1/settlements/candidates?as_of=not-a-date')
            ->assertStatus(422);
    }
}
