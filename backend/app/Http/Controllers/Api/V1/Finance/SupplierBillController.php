<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CancelSupplierBillRequest;
use App\Http\Requests\Finance\ListSupplierBillsRequest;
use App\Http\Requests\Finance\StoreSupplierBillRequest;
use App\Http\Requests\Finance\UpdateSupplierBillRequest;
use App\Http\Resources\Finance\SupplierBillResource;
use App\Services\Finance\SupplierBillService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierBillController extends Controller
{
    public function index(
        ListSupplierBillsRequest $request,
        SupplierBillService $service
    ): JsonResponse {
        $data =
            $request->validated();

        $paginator =
            $service->paginate(
                $data['search'] ?? null,
                $data['status'] ?? null,
                $data['supplier_id'] ?? null,
                $data['per_page'] ?? 20
            );

        $items =
            collect(
                $paginator->items()
            )
                ->map(
                    fn ($bill) =>
                        (
                            new SupplierBillResource(
                                $bill
                            )
                        )->resolve($request)
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

    public function store(
        StoreSupplierBillRequest $request,
        SupplierBillService $service
    ): JsonResponse {
        $bill =
            $service->create(
                $request->validated()
            );

        return ApiResponse::success(
            $request,
            (
                new SupplierBillResource(
                    $bill
                )
            )->resolve($request),
            201,
            'Tagihan Pemasok berhasil dibuat.'
        );
    }

    public function show(
        Request $request,
        string $supplierBillId,
        SupplierBillService $service
    ): JsonResponse {
        return ApiResponse::success(
            $request,
            (
                new SupplierBillResource(
                    $service->findOrFail(
                        $supplierBillId
                    )
                )
            )->resolve($request)
        );
    }

    public function update(
        UpdateSupplierBillRequest $request,
        string $supplierBillId,
        SupplierBillService $service
    ): JsonResponse {
        $bill =
            $service->update(
                $supplierBillId,
                $request->validated()
            );

        return ApiResponse::success(
            $request,
            (
                new SupplierBillResource(
                    $bill
                )
            )->resolve($request),
            200,
            'Tagihan Pemasok berhasil diperbarui.'
        );
    }

    public function post(
        Request $request,
        string $supplierBillId,
        SupplierBillService $service
    ): JsonResponse {
        $bill =
            $service->post(
                $supplierBillId
            );

        return ApiResponse::success(
            $request,
            (
                new SupplierBillResource(
                    $bill
                )
            )->resolve($request),
            200,
            'Tagihan Pemasok berhasil dicatat.'
        );
    }

    public function cancel(
        CancelSupplierBillRequest $request,
        string $supplierBillId,
        SupplierBillService $service
    ): JsonResponse {
        $bill =
            $service->cancel(
                $supplierBillId,
                $request->validated(
                    'reason'
                )
            );

        return ApiResponse::success(
            $request,
            (
                new SupplierBillResource(
                    $bill
                )
            )->resolve($request),
            200,
            'Tagihan Pemasok berhasil dibatalkan.'
        );
    }
}
