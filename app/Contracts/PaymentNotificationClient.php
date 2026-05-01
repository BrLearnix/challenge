<?php

namespace App\Contracts;

use App\Exceptions\PaymentNotificationTransportException;
use App\Models\Payment;

interface PaymentNotificationClient
{
    /**
     * Notifica al sistema externo que el pago quedó confirmado (simulación en desarrollo).
     *
     * @param  array<string, mixed>  $payload  Snapshop enviado al comercio/proveedor.
     * @param  int  $attemptNumber  Intento actual (1-based), útil para simular fallos.
     *
     * @throws PaymentNotificationTransportException
     */
    public function notifyPaymentConfirmed(Payment $payment, array $payload, int $attemptNumber): void;
}
