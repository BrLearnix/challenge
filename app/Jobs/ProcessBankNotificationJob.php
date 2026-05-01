<?php

namespace App\Jobs;

use App\Enums\BankNotificationProcessingOutcome;
use App\Enums\PaymentStatus;
use App\Models\BankNotification;
use App\Models\Payment;
use App\Models\PaymentAudit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class ProcessBankNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60, 120];
    }

    public function __construct(
        public int $bankNotificationId,
    ) {}

    public function handle(): void
    {
        DB::transaction(function (): void {
            $notification = BankNotification::query()
                ->whereKey($this->bankNotificationId)
                ->first();

            if ($notification === null) {
                return;
            }

            if ($notification->processed_at !== null) {
                return;
            }

            $payment = Payment::query()
                ->where('payment_code', $notification->payment_code)
                ->first();

            $notification->payment_id = $payment?->id;
            $notification->save();

            if ($payment === null) {
                $notification->update([
                    'processing_outcome' => BankNotificationProcessingOutcome::PAYMENT_NOT_FOUND->value,
                    'processed_at' => now(),
                ]);

                return;
            }

            if ($payment->status === PaymentStatus::PAID) {
                $notification->update([
                    'processing_outcome' => BankNotificationProcessingOutcome::IDEMPOTENT_ALREADY_PAID->value,
                    'processed_at' => now(),
                ]);

                return;
            }

            if ($payment->status !== PaymentStatus::PENDING) {
                $notification->update([
                    'processing_outcome' => BankNotificationProcessingOutcome::SKIPPED_INVALID_PAYMENT_STATE->value,
                    'processed_at' => now(),
                ]);

                PaymentAudit::query()->create([
                    'payment_id' => $payment->id,
                    'source' => 'bank_notification',
                    'action' => 'skipped_invalid_state',
                    'context' => [
                        'bank_notification_id' => $notification->id,
                        'event_id' => $notification->event_id,
                        'payment_status' => $payment->status->value,
                    ],
                ]);

                return;
            }

            $observationReasons = [];

            if ($notification->amount_minor !== $payment->amount_minor) {
                $observationReasons[] = 'monto_distinto';
            }

            if ($notification->currency !== $payment->currency) {
                $observationReasons[] = 'moneda_distinta';
            }

            if ($notification->reported_payment_status !== 'PAID') {
                $observationReasons[] = 'estado_banco_no_paid';
            }

            if ($observationReasons !== []) {
                $this->markObserved($payment, $notification, 'Discrepancia con la operación registrada.', [
                    'checks' => $observationReasons,
                    'notification_amount_minor' => $notification->amount_minor,
                    'payment_amount_minor' => $payment->amount_minor,
                ]);

                return;
            }

            $payment->update([
                'status' => PaymentStatus::PAID,
                'paid_at' => $notification->paid_at,
                'observed_reason' => null,
            ]);

            PaymentAudit::query()->create([
                'payment_id' => $payment->id,
                'source' => 'bank_notification',
                'action' => 'marked_paid',
                'context' => [
                    'bank_notification_id' => $notification->id,
                    'event_id' => $notification->event_id,
                    'bank_transaction_id' => $notification->bank_transaction_id,
                ],
            ]);

            $notification->update([
                'processing_outcome' => BankNotificationProcessingOutcome::PROCESSED_PAID->value,
                'processed_at' => now(),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function markObserved(Payment $payment, BankNotification $notification, string $reason, array $context = []): void
    {
        $payment->update([
            'status' => PaymentStatus::OBSERVED,
            'observed_reason' => Str::limit($reason, 500),
        ]);

        PaymentAudit::query()->create([
            'payment_id' => $payment->id,
            'source' => 'bank_notification',
            'action' => 'marked_observed',
            'context' => array_merge([
                'bank_notification_id' => $notification->id,
                'event_id' => $notification->event_id,
                'reason' => $reason,
            ], $context),
        ]);

        $notification->update([
            'processing_outcome' => BankNotificationProcessingOutcome::PROCESSED_OBSERVED->value,
            'processed_at' => now(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        BankNotification::query()->whereKey($this->bankNotificationId)->update([
            'processing_outcome' => BankNotificationProcessingOutcome::JOB_FAILED->value,
            'processed_at' => now(),
        ]);
    }
}
