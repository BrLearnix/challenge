<?php

namespace App\Jobs;

use App\Contracts\PaymentNotificationClient;
use App\Enums\ExternalNotificationStatus;
use App\Enums\PaymentStatus;
use App\Models\ExternalNotification;
use App\Models\Payment;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class NotifyPaymentConfirmedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [5, 30, 120];
    }

    public function __construct(
        public int $paymentId,
    ) {}

    public function handle(PaymentNotificationClient $client): void
    {
        $payment = Payment::query()->find($this->paymentId);

        if ($payment === null || $payment->paid_at === null) {
            return;
        }

        if (! in_array($payment->status, [PaymentStatus::PAID, PaymentStatus::RECONCILED], true)) {
            return;
        }

        $payload = $this->buildPayload($payment);

        $record = DB::transaction(function () use ($payment, $payload): ExternalNotification {
            $existing = ExternalNotification::query()
                ->where('payment_id', $payment->id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null && $existing->status === ExternalNotificationStatus::SENT) {
                return $existing;
            }

            if ($existing === null) {
                $existing = ExternalNotification::query()->create([
                    'payment_id' => $payment->id,
                    'status' => ExternalNotificationStatus::PENDING,
                    'attempt_count' => 0,
                    'payload_snapshot' => $payload,
                ]);
            } else {
                $existing->update([
                    'payload_snapshot' => $payload,
                    'status' => ExternalNotificationStatus::PENDING,
                ]);
            }

            $existing->increment('attempt_count');
            $existing->refresh();

            $existing->update([
                'last_attempt_at' => now(),
            ]);

            return $existing->fresh();
        });

        if ($record->status === ExternalNotificationStatus::SENT) {
            return;
        }

        try {
            $client->notifyPaymentConfirmed($payment, $payload, $record->attempt_count);

            $record->update([
                'status' => ExternalNotificationStatus::SENT,
                'last_error' => null,
            ]);
        } catch (Throwable $e) {
            $record->update([
                'status' => ExternalNotificationStatus::FAILED,
                'last_error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(Payment $payment): array
    {
        return [
            'payment_code' => $payment->payment_code,
            'status' => $payment->status->value,
            'amount' => Money::minorToDecimalString((int) $payment->amount_minor),
            'currency' => $payment->currency,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'merchant_id' => $payment->merchant_id,
        ];
    }

    public function failed(?Throwable $exception): void
    {
        ExternalNotification::query()
            ->where('payment_id', $this->paymentId)
            ->update([
                'status' => ExternalNotificationStatus::FAILED->value,
                'last_error' => $exception?->getMessage() ?? 'Job failed',
            ]);
    }
}
