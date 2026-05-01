<?php

namespace Tests\Feature\Api\V1;

use App\Enums\ExternalNotificationStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentNotificationTransportException;
use App\Jobs\NotifyPaymentConfirmedJob;
use App\Models\ExternalNotification;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class NotifyPaymentConfirmedJobTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Config::set('services.payment_notification.simulate_transport_error', false);

        parent::tearDown();
    }

    public function test_retry_after_simulated_transport_failure(): void
    {
        Config::set('services.payment_notification.simulate_transport_error', true);

        $payment = Payment::factory()->create([
            'status' => PaymentStatus::PAID,
            'paid_at' => now(),
            'amount_minor' => 15050,
            'currency' => 'PEN',
        ]);

        try {
            Bus::dispatchSync(new NotifyPaymentConfirmedJob($payment->id));
            $this->fail('Expected transport exception on first attempt.');
        } catch (PaymentNotificationTransportException $e) {
            $this->assertStringContainsString('Simulación', $e->getMessage());
        }

        $external = ExternalNotification::query()->where('payment_id', $payment->id)->firstOrFail();
        $this->assertSame(ExternalNotificationStatus::FAILED, $external->status);
        $this->assertSame(1, $external->attempt_count);

        Bus::dispatchSync(new NotifyPaymentConfirmedJob($payment->id));

        $external->refresh();
        $this->assertSame(ExternalNotificationStatus::SENT, $external->status);
        $this->assertSame(2, $external->attempt_count);
    }

    public function test_skips_notify_when_already_sent(): void
    {
        $payment = Payment::factory()->create([
            'status' => PaymentStatus::PAID,
            'paid_at' => now(),
        ]);

        ExternalNotification::query()->create([
            'payment_id' => $payment->id,
            'status' => ExternalNotificationStatus::SENT,
            'attempt_count' => 1,
            'payload_snapshot' => [],
        ]);

        Bus::dispatchSync(new NotifyPaymentConfirmedJob($payment->id));

        $external = ExternalNotification::query()->where('payment_id', $payment->id)->firstOrFail();
        $this->assertSame(1, $external->attempt_count);
        $this->assertSame(ExternalNotificationStatus::SENT, $external->status);
    }
}
