<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ReceiveBankNotificationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBankNotificationRequest;
use Illuminate\Http\JsonResponse;

class BankNotificationController extends Controller
{
    public function store(
        StoreBankNotificationRequest $request,
        ReceiveBankNotificationAction $action,
    ): JsonResponse {
        $rawPayload = json_decode($request->getContent(), true);

        if (! is_array($rawPayload)) {
            return response()->json([
                'error' => 'invalid_json',
                'message' => 'El cuerpo no es un JSON válido.',
            ], 400);
        }

        return $action->execute($request->validated(), $rawPayload);
    }
}
