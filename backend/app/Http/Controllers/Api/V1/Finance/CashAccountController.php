<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ListCashAccountsRequest;
use App\Http\Requests\Finance\StoreCashAccountRequest;
use App\Http\Requests\Finance\UpdateCashAccountRequest;
use App\Http\Resources\Finance\CashAccountResource;
use App\Services\Finance\CashAccountService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashAccountController extends Controller
{
    public function index(
        ListCashAccountsRequest $request,
        CashAccountService $service
    ): JsonResponse {
        $data = $request->validated();

        $paginator =
            $service->paginate(
                $data['search'] ?? null,
                $data['status'] ?? null,
                $data['type'] ?? null,
                $data['per_page'] ?? 20
            );

        $items = collect(
            $paginator->items()
        )
            ->map(
                fn ($account) =>
                    (new CashAccountResource(
                        $account
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
        StoreCashAccountRequest $request,
        CashAccountService $service
    ): JsonResponse {
        $account =
            $service->create(
                $request->validated()
            );

        return ApiResponse::success(
            $request,
            (new CashAccountResource(
                $account
            ))->resolve($request),
            201,
            'Rekening Kas & Bank berhasil dibuat.'
        );
    }

    public function show(
        Request $request,
        string $cashAccountId,
        CashAccountService $service
    ): JsonResponse {
        return ApiResponse::success(
            $request,
            (new CashAccountResource(
                $service->findOrFail(
                    $cashAccountId
                )
            ))->resolve($request)
        );
    }

    public function update(
        UpdateCashAccountRequest $request,
        string $cashAccountId,
        CashAccountService $service
    ): JsonResponse {
        $account =
            $service->update(
                $cashAccountId,
                $request->validated()
            );

        return ApiResponse::success(
            $request,
            (new CashAccountResource(
                $account
            ))->resolve($request),
            200,
            'Rekening Kas & Bank berhasil diperbarui.'
        );
    }

    public function activate(
        Request $request,
        string $cashAccountId,
        CashAccountService $service
    ): JsonResponse {
        $account =
            $service->activate(
                $cashAccountId
            );

        return ApiResponse::success(
            $request,
            (new CashAccountResource(
                $account
            ))->resolve($request),
            200,
            'Rekening berhasil diaktifkan.'
        );
    }

    public function deactivate(
        Request $request,
        string $cashAccountId,
        CashAccountService $service
    ): JsonResponse {
        $account =
            $service->deactivate(
                $cashAccountId
            );

        return ApiResponse::success(
            $request,
            (new CashAccountResource(
                $account
            ))->resolve($request),
            200,
            'Rekening berhasil dinonaktifkan.'
        );
    }

    public function setDefault(
        Request $request,
        string $cashAccountId,
        CashAccountService $service
    ): JsonResponse {
        $account =
            $service->setDefault(
                $cashAccountId
            );

        return ApiResponse::success(
            $request,
            (new CashAccountResource(
                $account
            ))->resolve($request),
            200,
            'Rekening default berhasil diperbarui.'
        );
    }

    public function destroy(
        Request $request,
        string $cashAccountId,
        CashAccountService $service
    ): JsonResponse {
        $service->delete(
            $cashAccountId
        );

        return ApiResponse::success(
            $request,
            [
                'id' =>
                    $cashAccountId,
            ],
            200,
            'Rekening Kas & Bank berhasil dihapus.'
        );
    }
}
