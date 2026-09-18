<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ListIncomeRegisterRequest;
use App\Http\Resources\Finance\IncomeRegisterResource;
use App\Services\Finance\IncomeRegisterService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class IncomeRegisterController extends Controller
{
    public function __invoke(
        ListIncomeRegisterRequest $request,
        IncomeRegisterService $service
    ): JsonResponse {
        $data =
            $request->validated();

        $paginator =
            $service->paginate(
                $data['search'] ?? null,
                $data['group'] ?? null,
                $data['from'] ?? null,
                $data['to'] ?? null,
                $data['cash_account_id']
                    ?? null,
                $data['per_page'] ?? 20
            );

        $items =
            collect(
                $paginator->items()
            )
                ->map(
                    fn ($item) =>
                        (
                            new IncomeRegisterResource(
                                $item
                            )
                        )->resolve(
                            $request
                        )
                )
                ->values()
                ->all();

        return ApiResponse::success(
            $request,
            $items,
            200,
            null,
            [
                'current_page' =>
                    $paginator
                        ->currentPage(),

                'per_page' =>
                    $paginator
                        ->perPage(),

                'total' =>
                    $paginator
                        ->total(),

                'last_page' =>
                    $paginator
                        ->lastPage(),
            ]
        );
    }
}
