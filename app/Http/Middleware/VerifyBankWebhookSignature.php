<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyBankWebhookSignature
{
    /**
     * Valida Bearer token (secret compartido) o HMAC-SHA256 del cuerpo crudo en cabecera X-Bank-Signature.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.bank_webhook.secret');

        if ($secret === null || $secret === '') {
            return response()->json([
                'error' => 'configuration',
                'message' => 'BANK_WEBHOOK_SECRET no está configurado en el servidor.',
            ], 503);
        }

        $authorization = (string) $request->header('Authorization', '');
        if (str_starts_with($authorization, 'Bearer ')) {
            $token = substr($authorization, 7);
            if (hash_equals($secret, $token)) {
                return $next($request);
            }
        }

        $signature = $request->header('X-Bank-Signature');
        if ($signature !== null && $signature !== '') {
            $expected = hash_hmac('sha256', $request->getContent(), $secret);
            if (hash_equals($expected, $signature)) {
                return $next($request);
            }
        }

        return response()->json([
            'error' => 'unauthorized',
            'message' => 'Token Bearer o firma X-Bank-Signature inválidos.',
            'hint' => 'Usa Authorization: Bearer <BANK_WEBHOOK_SECRET> o X-Bank-Signature con HMAC-SHA256 hexadecimal del body crudo.',
        ], 401);
    }
}
