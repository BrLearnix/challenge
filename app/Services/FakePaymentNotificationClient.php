<?php

namespace App\Services;

use App\Contracts\PaymentNotificationClient;
use App\Exceptions\PaymentNotificationTransportException;
use App\Models\Payment;

/**
 * Simula una integración HTTP con timeout / 5xx / errores transitorios (solo desarrollo / pruebas).
 * En producción se reemplazaría por un cliente Guzzle u otro con URL real, timeouts y circuit breaker.
 */
class FakePaymentNotificationClient implements PaymentNotificationClient
{
    public function notifyPaymentConfirmed(Payment $payment, array $payload, int $attemptNumber): void
    {
        if (config('services.payment_notification.simulate_transport_error') && $attemptNumber === 1) {
            throw new PaymentNotificationTransportException(
                'Simulación: error transitorio del proveedor (p. ej. 503 o timeout).'
            );
        }

        logger()->info('payment_notification.fake.sent', [
            'payment_id' => $payment->id,
            'payment_code' => $payment->payment_code,
            'attempt' => $attemptNumber,
            'payload_keys' => array_keys($payload),
        ]);
    }
}
