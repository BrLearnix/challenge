<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreatePaymentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function store(StorePaymentRequest $request, CreatePaymentAction $action): JsonResponse
    {
        $payment = $action->execute($request->validated());

        return PaymentResource::make($payment)
            ->response()
            ->setStatusCode(201);
    }
}
