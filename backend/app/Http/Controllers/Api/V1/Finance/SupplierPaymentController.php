<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ListSupplierPaymentsRequest;
use App\Http\Requests\Finance\ReverseSupplierPaymentRequest;
use App\Http\Requests\Finance\StoreSupplierPaymentRequest;
use App\Http\Requests\Finance\UpdateSupplierPaymentRequest;
use App\Http\Resources\Finance\SupplierPaymentResource;
use App\Services\Finance\SupplierPaymentService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierPaymentController extends Controller
{
    public function index(
        ListSupplierPaymentsRequest $request,
        SupplierPaymentService $service
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
                    fn ($payment) =>
                        (
                            new SupplierPaymentResource(
                                $payment
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
        StoreSupplierPaymentRequest $request,
        SupplierPaymentService $service
    ): JsonResponse {
        $payment =
            $service->create(
                $request->validated()
            );

        return ApiResponse::success(
            $request,
            (
                new SupplierPaymentResource(
                    $payment
                )
            )->resolve($request),
            201,
            'Pembayaran Pemasok berhasil dibuat.'
        );
    }

    public function show(
        Request $request,
        string $supplierPaymentId,
        SupplierPaymentService $service
    ): JsonResponse {
        return ApiResponse::success(
            $request,
            (
                new SupplierPaymentResource(
                    $service->findOrFail(
                        $supplierPaymentId
                    )
                )
            )->resolve($request)
        );
    }

    public function update(
        UpdateSupplierPaymentRequest $request,
        string $supplierPaymentId,
        SupplierPaymentService $service
    ): JsonResponse {
        $payment =
            $service->update(
                $supplierPaymentId,
                $request->validated()
            );

        return ApiResponse::success(
            $request,
            (
                new SupplierPaymentResource(
                    $payment
                )
            )->resolve($request),
            200,
            'Pembayaran Pemasok berhasil diperbarui.'
        );
    }

    public function post(
        Request $request,
        string $supplierPaymentId,
        SupplierPaymentService $service
    ): JsonResponse {
        $payment =
            $service->post(
                $supplierPaymentId
            );

        return ApiResponse::success(
            $request,
            (
                new SupplierPaymentResource(
                    $payment
                )
            )->resolve($request),
            200,
            'Pembayaran Pemasok berhasil dicatat.'
        );
    }

    public function reverse(
        ReverseSupplierPaymentRequest $request,
        string $supplierPaymentId,
        SupplierPaymentService $service
    ): JsonResponse {
        $payment =
            $service->reverse(
                $supplierPaymentId,
                $request->validated(
                    'reason'
                )
            );

        return ApiResponse::success(
            $request,
            (
                new SupplierPaymentResource(
                    $payment
                )
            )->resolve($request),
            200,
            'Pembayaran Pemasok berhasil dikoreksi.'
        );
    }
}
