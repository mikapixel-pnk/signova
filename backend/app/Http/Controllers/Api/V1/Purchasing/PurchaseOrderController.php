<?php

namespace App\Http\Controllers\Api\V1\Purchasing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\ListPurchaseOrdersRequest;
use App\Http\Requests\Purchasing\PurchaseOrderReasonRequest;
use App\Http\Requests\Purchasing\StorePurchaseOrderRequest;
use App\Http\Requests\Purchasing\UpdatePurchaseOrderRequest;
use App\Http\Resources\Purchasing\PurchaseOrderResource;
use App\Services\Purchasing\PurchaseOrderService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function index(
        ListPurchaseOrdersRequest $request,
        PurchaseOrderService $service
    ): JsonResponse {
        $validated =
            $request->validated();

        $paginator =
            $service->paginate(
                $validated['search']
                    ?? null,

                $validated['status']
                    ?? null,

                $validated['supplier_id']
                    ?? null,

                $validated['per_page']
                    ?? 20
            );

        $data =
            collect(
                $paginator->items()
            )
                ->map(
                    fn ($purchaseOrder) =>
                        (
                            new PurchaseOrderResource(
                                $purchaseOrder
                            )
                        )->resolve($request)
                )
                ->values()
                ->all();

        return ApiResponse::success(
            $request,
            $data,
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
        StorePurchaseOrderRequest $request,
        PurchaseOrderService $service
    ): JsonResponse {
        $purchaseOrder =
            $service->createDraft(
                $request->validated()
            );

        return ApiResponse::success(
            $request,
            (
                new PurchaseOrderResource(
                    $purchaseOrder
                )
            )->resolve($request),
            201,
            'Pesanan Pembelian berhasil dibuat.'
        );
    }

    public function show(
        Request $request,
        string $purchaseOrderId,
        PurchaseOrderService $service
    ): JsonResponse {
        return ApiResponse::success(
            $request,
            (
                new PurchaseOrderResource(
                    $service->findOrFail(
                        $purchaseOrderId
                    )
                )
            )->resolve($request)
        );
    }

    public function update(
        UpdatePurchaseOrderRequest $request,
        string $purchaseOrderId,
        PurchaseOrderService $service
    ): JsonResponse {
        $purchaseOrder =
            $service->updateDraft(
                $purchaseOrderId,
                $request->validated()
            );

        return ApiResponse::success(
            $request,
            (
                new PurchaseOrderResource(
                    $purchaseOrder
                )
            )->resolve($request),
            200,
            'Draf Pesanan Pembelian berhasil diperbarui.'
        );
    }

    public function issue(
        Request $request,
        string $purchaseOrderId,
        PurchaseOrderService $service
    ): JsonResponse {
        return ApiResponse::success(
            $request,
            (
                new PurchaseOrderResource(
                    $service->issue(
                        $purchaseOrderId
                    )
                )
            )->resolve($request),
            200,
            'Pesanan Pembelian berhasil diterbitkan.'
        );
    }

    public function cancel(
        PurchaseOrderReasonRequest $request,
        string $purchaseOrderId,
        PurchaseOrderService $service
    ): JsonResponse {
        return ApiResponse::success(
            $request,
            (
                new PurchaseOrderResource(
                    $service->cancel(
                        $purchaseOrderId,
                        $request->validated(
                            'reason'
                        )
                    )
                )
            )->resolve($request),
            200,
            'Pesanan Pembelian berhasil dibatalkan.'
        );
    }
}
