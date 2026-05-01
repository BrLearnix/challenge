<?php

namespace App\Actions;

use App\Enums\BankNotificationProcessingOutcome;
use App\Jobs\ProcessBankNotificationJob;
use App\Models\BankNotification;
use App\Support\Money;
use Illuminate\Http\JsonResponse;

class ReceiveBankNotificationAction
{
    /**
     * @param  array<string, mixed>  $validated  Datos validados del JSON
     * @param  array<string, mixed>  $rawPayload  Payload original decodificado (trazabilidad)
     */
    public function execute(array $validated, array $rawPayload): JsonResponse
    {
        $eventId = $validated['event_id'];
        $bankTxnId = $validated['bank_transaction_id'];

        if (BankNotification::query()->where('event_id', $eventId)->exists()) {
            return response()->json([
                'duplicate_event_id' => true,
                'message' => 'Este evento ya fue registrado; no se reprocesa.',
            ], 200);
        }

        if (BankNotification::query()->where('bank_transaction_id', $bankTxnId)->exists()) {
            return response()->json([
                'duplicate_bank_transaction_id' => true,
                'message' => 'Este bank_transaction_id ya fue registrado; no se duplica el efecto sobre el pago.',
            ], 200);
        }

        $notification = BankNotification::query()->create([
            'event_id' => $eventId,
            'bank_transaction_id' => $bankTxnId,
            'payment_code' => $validated['payment_code'],
            'payload' => $rawPayload,
            'amount_minor' => Money::toMinorUnits($validated['amount']),
            'currency' => $validated['currency'],
            'reported_payment_status' => $validated['status'],
            'paid_at' => $validated['paid_at'],
            'processing_outcome' => BankNotificationProcessingOutcome::QUEUED->value,
        ]);

        ProcessBankNotificationJob::dispatch($notification->id);

        return response()->json([
            'bank_notification_id' => $notification->id,
            'status' => BankNotificationProcessingOutcome::QUEUED->value,
            'message' => 'Notificación aceptada; el procesamiento continúa en cola.',
        ], 202);
    }
}
