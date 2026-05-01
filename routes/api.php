<?php

use App\Http\Controllers\Api\V1\BankNotificationController;
use App\Http\Controllers\Api\V1\BankReconciliationController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\SettlementCandidateController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('payments', [PaymentController::class, 'store']);

    Route::get('settlements/candidates', [SettlementCandidateController::class, 'index']);

    Route::post('bank/notifications', [BankNotificationController::class, 'store'])
        ->middleware('bank.webhook');

    Route::post('bank/reconciliation', [BankReconciliationController::class, 'store'])
        ->middleware('bank.webhook');
});
