<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ProcessBankReconciliationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBankReconciliationRequest;
use Illuminate\Http\JsonResponse;

class BankReconciliationController extends Controller
{
    public function store(
        StoreBankReconciliationRequest $request,
        ProcessBankReconciliationAction $action,
    ): JsonResponse {
        $validated = $request->validated();

        $result = $action->execute(
            $validated['bank'],
            $validated['process_date'],
            $validated['movements'],
        );

        return response()->json($result, 200);
    }
}
