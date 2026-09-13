<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ListExpensesRequest;
use App\Http\Requests\Finance\StoreExpenseRequest;
use App\Http\Requests\Finance\UpdateExpenseRequest;
use App\Http\Requests\Finance\VoidExpenseRequest;
use App\Http\Resources\Finance\ExpenseResource;
use App\Services\Finance\ExpenseService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index(
        ListExpensesRequest $request,
        ExpenseService $service
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
                fn ($expense) =>
                    (new ExpenseResource(
                        $expense
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
        StoreExpenseRequest $request,
        ExpenseService $service
    ): JsonResponse {
        $expense =
            $service->create(
                $request->validated()
            );

        return ApiResponse::success(
            $request,
            (new ExpenseResource(
                $expense
            ))->resolve($request),
            201,
            'Pengeluaran berhasil dibuat.'
        );
    }

    public function show(
        Request $request,
        string $expenseId,
        ExpenseService $service
    ): JsonResponse {
        return ApiResponse::success(
            $request,
            (new ExpenseResource(
                $service->findOrFail(
                    $expenseId
                )
            ))->resolve($request)
        );
    }

    public function update(
        UpdateExpenseRequest $request,
        string $expenseId,
        ExpenseService $service
    ): JsonResponse {
        $expense =
            $service->update(
                $expenseId,
                $request->validated()
            );

        return ApiResponse::success(
            $request,
            (new ExpenseResource(
                $expense
            ))->resolve($request),
            200,
            'Pengeluaran berhasil diperbarui.'
        );
    }

    public function destroy(
        Request $request,
        string $expenseId,
        ExpenseService $service
    ): JsonResponse {
        $service->delete(
            $expenseId
        );

        return ApiResponse::success(
            $request,
            [
                'id' =>
                    $expenseId,
            ],
            200,
            'Pengeluaran berhasil dihapus.'
        );
    }

    public function post(
        Request $request,
        string $expenseId,
        ExpenseService $service
    ): JsonResponse {
        $expense =
            $service->post(
                $expenseId
            );

        return ApiResponse::success(
            $request,
            (new ExpenseResource(
                $expense
            ))->resolve($request),
            200,
            'Pengeluaran berhasil diposting.'
        );
    }

    public function void(
        VoidExpenseRequest $request,
        string $expenseId,
        ExpenseService $service
    ): JsonResponse {
        $expense =
            $service->void(
                $expenseId,
                $request->validated()['reason']
            );

        return ApiResponse::success(
            $request,
            (new ExpenseResource(
                $expense
            ))->resolve($request),
            200,
            'Pengeluaran berhasil dibatalkan.'
        );
    }
}
