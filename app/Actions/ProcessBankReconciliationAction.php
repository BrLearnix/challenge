<?php

namespace App\Actions;

use App\Enums\BankNotificationProcessingOutcome;
use App\Enums\PaymentStatus;
use App\Enums\ReconciliationMovementOutcome;
use App\Models\BankNotification;
use App\Models\BankReconciliationBatch;
use App\Models\BankReconciliationMovement;
use App\Models\Payment;
use App\Models\PaymentAudit;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class ProcessBankReconciliationAction
{
    /**
     * @param  array<int, array<string, mixed>>  $movements
     * @return array{batch_id: int, summary: array<string, int>, movements: array<int, array<string, mixed>>}
     */
    public function execute(string $bank, string $processDate, array $movements): array
    {
        return DB::transaction(function () use ($bank, $processDate, $movements): array {
            $batch = BankReconciliationBatch::query()->firstOrCreate(
                [
                    'bank' => $bank,
                    'process_date' => $processDate,
                ],
                []
            );

            $summary = [
                'matched' => 0,
                'discrepancy' => 0,
                'unmatched' => 0,
                'duplicates_skipped' => 0,
            ];

            $details = [];

            foreach ($movements as $row) {
                $result = $this->processMovementRow($batch, $row);
                $details[] = $result['detail'];

                if ($result['bucket'] === 'duplicate') {
                    $summary['duplicates_skipped']++;
                } else {
                    $summary[$result['bucket']]++;
                }
            }

            return [
                'batch_id' => $batch->id,
                'summary' => $summary,
                'movements' => $details,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{bucket: string, detail: array<string, mixed>}
     */
    private function processMovementRow(BankReconciliationBatch $batch, array $row): array
    {
        $bankMovementId = $row['bank_movement_id'];

        $already = BankReconciliationMovement::query()
            ->where('bank_reconciliation_batch_id', $batch->id)
            ->where('bank_movement_id', $bankMovementId)
            ->exists();

        if ($already) {
            return [
                'bucket' => 'duplicate',
                'detail' => [
                    'bank_movement_id' => $bankMovementId,
                    'outcome' => ReconciliationMovementOutcome::DUPLICATE_BATCH->value,
                    'message' => 'Este bank_movement_id ya fue procesado en el lote.',
                ],
            ];
        }

        $movement = BankReconciliationMovement::query()->create([
            'bank_reconciliation_batch_id' => $batch->id,
            'bank_movement_id' => $bankMovementId,
            'bank_transaction_id' => $row['bank_transaction_id'] ?? null,
            'payment_code' => $row['payment_code'] ?? null,
            'amount_minor' => Money::toMinorUnits($row['amount']),
            'currency' => $row['currency'],
            'paid_at' => $row['paid_at'] ?? null,
            'outcome' => ReconciliationMovementOutcome::PENDING,
        ]);

        $paymentCode = $row['payment_code'] ?? null;
        if ($paymentCode === null || $paymentCode === '') {
            $this->finishUnmatched($movement, ['reason' => 'missing_payment_code']);

            return [
                'bucket' => 'unmatched',
                'detail' => $this->movementDetail($movement, 'Código de pago ausente.'),
            ];
        }

        $payment = Payment::query()->where('payment_code', $paymentCode)->first();

        if ($payment === null) {
            $this->finishUnmatched($movement, ['reason' => 'payment_not_found', 'payment_code' => $paymentCode]);

            return [
                'bucket' => 'unmatched',
                'detail' => $this->movementDetail($movement, 'No existe operación con ese payment_code.'),
            ];
        }

        $movement->payment_id = $payment->id;
        $movement->save();

        if (! $this->movementMatchesPaymentAmounts($movement, $payment)) {
            $this->finishDiscrepancy($payment, $movement, [
                'reason' => 'amount_or_currency_mismatch',
                'movement_amount_minor' => $movement->amount_minor,
                'payment_amount_minor' => $payment->amount_minor,
            ]);

            return [
                'bucket' => 'discrepancy',
                'detail' => $this->movementDetail($movement, 'Monto o moneda distintos a la operación.'),
            ];
        }

        if ($payment->status === PaymentStatus::OBSERVED) {
            $this->finishDiscrepancy($payment, $movement, [
                'reason' => 'payment_observed',
            ]);

            return [
                'bucket' => 'discrepancy',
                'detail' => $this->movementDetail($movement, 'La operación está en estado OBSERVED.'),
            ];
        }

        if ($payment->status === PaymentStatus::RECONCILED && $payment->reconciliation_match === 'MATCHED') {
            $this->finishMatchedIdempotent($payment, $movement);

            return [
                'bucket' => 'matched',
                'detail' => $this->movementDetail($movement, 'Conciliación idempotente (ya estaba RECONCILED).'),
            ];
        }

        if ($payment->status === PaymentStatus::PENDING) {
            $this->applyLateConfirmation($payment, $movement);

            return [
                'bucket' => 'matched',
                'detail' => $this->movementDetail($movement, 'Confirmación tardía: marcado PAID y RECONCILED desde cierre.'),
            ];
        }

        if ($payment->status !== PaymentStatus::PAID) {
            $this->finishDiscrepancy($payment, $movement, [
                'reason' => 'unexpected_payment_status',
                'status' => $payment->status->value,
            ]);

            return [
                'bucket' => 'discrepancy',
                'detail' => $this->movementDetail($movement, 'Estado del pago no admite conciliación automática.'),
            ];
        }

        if ($movement->bank_transaction_id === null || $movement->bank_transaction_id === '') {
            $this->finishDiscrepancy($payment, $movement, [
                'reason' => 'missing_bank_transaction_id',
            ]);

            return [
                'bucket' => 'discrepancy',
                'detail' => $this->movementDetail($movement, 'Falta bank_transaction_id para validar tiempo real.'),
            ];
        }

        $realtimeOk = BankNotification::query()
            ->where('payment_id', $payment->id)
            ->where('bank_transaction_id', $movement->bank_transaction_id)
            ->where('processing_outcome', BankNotificationProcessingOutcome::PROCESSED_PAID)
            ->exists();

        if (! $realtimeOk) {
            $this->finishDiscrepancy($payment, $movement, [
                'reason' => 'no_matching_realtime_confirmation',
                'bank_transaction_id' => $movement->bank_transaction_id,
            ]);

            return [
                'bucket' => 'discrepancy',
                'detail' => $this->movementDetail($movement, 'No hay confirmación en tiempo real PAID con ese bank_transaction_id.'),
            ];
        }

        $this->finishMatched($payment, $movement, [
            'matched_realtime' => true,
        ]);

        return [
            'bucket' => 'matched',
            'detail' => $this->movementDetail($movement, 'Coincide con confirmación en tiempo real.'),
        ];
    }

    private function movementMatchesPaymentAmounts(BankReconciliationMovement $movement, Payment $payment): bool
    {
        return $movement->amount_minor === $payment->amount_minor
            && $movement->currency === $payment->currency;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function finishUnmatched(BankReconciliationMovement $movement, array $metadata): void
    {
        $movement->update([
            'outcome' => ReconciliationMovementOutcome::UNMATCHED,
            'metadata' => $metadata,
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function finishDiscrepancy(Payment $payment, BankReconciliationMovement $movement, array $metadata): void
    {
        $movement->update([
            'outcome' => ReconciliationMovementOutcome::DISCREPANCY,
            'metadata' => $metadata,
        ]);

        $payment->update([
            'reconciliation_match' => 'DISCREPANCY',
        ]);

        PaymentAudit::query()->create([
            'payment_id' => $payment->id,
            'source' => 'bank_reconciliation',
            'action' => 'reconciliation_discrepancy',
            'context' => array_merge([
                'bank_reconciliation_movement_id' => $movement->id,
                'bank_movement_id' => $movement->bank_movement_id,
            ], $metadata),
        ]);
    }

    /**
     * @param  array<string, mixed>  $extraMeta
     */
    private function finishMatched(Payment $payment, BankReconciliationMovement $movement, array $extraMeta): void
    {
        $payment->update([
            'status' => PaymentStatus::RECONCILED,
            'reconciliation_match' => 'MATCHED',
        ]);

        $movement->update([
            'outcome' => ReconciliationMovementOutcome::MATCHED,
            'metadata' => $extraMeta,
        ]);

        PaymentAudit::query()->create([
            'payment_id' => $payment->id,
            'source' => 'bank_reconciliation',
            'action' => 'reconciliation_matched',
            'context' => array_merge([
                'bank_reconciliation_movement_id' => $movement->id,
                'bank_movement_id' => $movement->bank_movement_id,
            ], $extraMeta),
        ]);
    }

    private function finishMatchedIdempotent(Payment $payment, BankReconciliationMovement $movement): void
    {
        $movement->update([
            'outcome' => ReconciliationMovementOutcome::MATCHED,
            'metadata' => ['idempotent' => true],
        ]);

        PaymentAudit::query()->create([
            'payment_id' => $payment->id,
            'source' => 'bank_reconciliation',
            'action' => 'reconciliation_matched_idempotent',
            'context' => [
                'bank_reconciliation_movement_id' => $movement->id,
                'bank_movement_id' => $movement->bank_movement_id,
            ],
        ]);
    }

    private function applyLateConfirmation(Payment $payment, BankReconciliationMovement $movement): void
    {
        $payment->update([
            'status' => PaymentStatus::PAID,
            'paid_at' => $movement->paid_at ?? $payment->paid_at ?? now(),
            'observed_reason' => null,
        ]);

        PaymentAudit::query()->create([
            'payment_id' => $payment->id,
            'source' => 'bank_reconciliation',
            'action' => 'late_confirmation_from_closing',
            'context' => [
                'bank_reconciliation_movement_id' => $movement->id,
                'bank_movement_id' => $movement->bank_movement_id,
            ],
        ]);

        $payment->refresh();

        $this->finishMatched($payment, $movement, [
            'late_confirmation' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function movementDetail(BankReconciliationMovement $movement, string $message): array
    {
        return [
            'bank_movement_id' => $movement->bank_movement_id,
            'payment_code' => $movement->payment_code,
            'outcome' => $movement->fresh()->outcome->value,
            'message' => $message,
        ];
    }
}
