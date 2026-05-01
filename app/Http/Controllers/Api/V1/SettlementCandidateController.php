<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ListSettlementCandidatesAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\SettlementCandidatesRequest;
use App\Http\Resources\SettlementCandidateResource;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class SettlementCandidateController extends Controller
{
    public function index(SettlementCandidatesRequest $request, ListSettlementCandidatesAction $action): JsonResponse
    {
        $tz = config('app.timezone');
        $asOfInput = $request->validated('as_of');

        $asOf = $asOfInput !== null
            ? Carbon::parse($asOfInput, $tz)->startOfDay()
            : now($tz)->startOfDay();

        $merchantId = $request->validated('merchant_id');

        $rows = $action->execute($asOf, $merchantId);

        return response()->json([
            'as_of' => $asOf->toDateString(),
            'timezone' => $tz,
            'cutoff_time' => '20:45',
            'candidates' => SettlementCandidateResource::collection($rows),
        ]);
    }
}
