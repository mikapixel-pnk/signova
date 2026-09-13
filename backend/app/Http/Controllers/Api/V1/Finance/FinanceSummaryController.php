<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\FinanceSummaryRequest;
use App\Services\Finance\FinanceSummaryService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class FinanceSummaryController extends Controller
{
    public function __invoke(
        FinanceSummaryRequest $request,
        FinanceSummaryService $service
    ): JsonResponse {
        $data = $request->validated();

        return ApiResponse::success(
            $request,
            $service->summary(
                $data['from'] ?? null,
                $data['to'] ?? null
            )
        );
    }
}
