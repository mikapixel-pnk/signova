<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ListIncomesRequest;
use App\Http\Requests\Finance\StoreIncomeRequest;
use App\Http\Requests\Finance\UpdateIncomeRequest;
use App\Http\Requests\Finance\VoidIncomeRequest;
use App\Http\Resources\Finance\IncomeResource;
use App\Services\Finance\IncomeService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IncomeController extends Controller
{
    public function index(
        ListIncomesRequest $request,
        IncomeService $service
    ): JsonResponse {
        $data = $request->validated();

        $paginator =
            $service->paginate(
                $data['search'] ?? null,
                $data['status'] ?? null,
                $data['cash_account_id'] ?? null,
                $data['per_page'] ?? 20
            );

        $items = collect(
            $paginator->items()
        )
            ->map(
                fn ($income) =>
                    (new IncomeResource(
                        $income
                    ))->resolve($request)
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
                    $paginator->currentPage(),
                'per_page' =>
                    $paginator->perPage(),
                'total' =>
                    $paginator->total(),
                'last_page' =>
                    $paginator->lastPage(),
            ]
        );
    }

    public function store(
        StoreIncomeRequest $request,
        IncomeService $service
    ): JsonResponse {
        $income =
            $service->create(
                $request->validated()
            );

        return ApiResponse::success(
            $request,
            (new IncomeResource(
                $income
            ))->resolve($request),
            201,
            'Pemasukan berhasil dibuat.'
        );
    }

    public function show(
        Request $request,
        string $incomeId,
        IncomeService $service
    ): JsonResponse {
        return ApiResponse::success(
            $request,
            (new IncomeResource(
                $service->findOrFail(
                    $incomeId
                )
            ))->resolve($request)
        );
    }

    public function update(
        UpdateIncomeRequest $request,
        string $incomeId,
        IncomeService $service
    ): JsonResponse {
        $income =
            $service->update(
                $incomeId,
                $request->validated()
            );

        return ApiResponse::success(
            $request,
            (new IncomeResource(
                $income
            ))->resolve($request),
            200,
            'Pemasukan berhasil diperbarui.'
        );
    }

    public function destroy(
        Request $request,
        string $incomeId,
        IncomeService $service
    ): JsonResponse {
        $service->delete(
            $incomeId
        );

        return ApiResponse::success(
            $request,
            [
                'id' =>
                    $incomeId,
            ],
            200,
            'Pemasukan berhasil dihapus.'
        );
    }

    public function post(
        Request $request,
        string $incomeId,
        IncomeService $service
    ): JsonResponse {
        $income =
            $service->post(
                $incomeId
            );

        return ApiResponse::success(
            $request,
            (new IncomeResource(
                $income
            ))->resolve($request),
            200,
            'Pemasukan berhasil diposting.'
        );
    }

    public function void(
        VoidIncomeRequest $request,
        string $incomeId,
        IncomeService $service
    ): JsonResponse {
        $income =
            $service->void(
                $incomeId,
                $request->validated()['reason']
            );

        return ApiResponse::success(
            $request,
            (new IncomeResource(
                $income
            ))->resolve($request),
            200,
            'Pemasukan berhasil dibatalkan.'
        );
    }
}
