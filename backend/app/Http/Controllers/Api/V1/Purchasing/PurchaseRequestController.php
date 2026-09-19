<?php

namespace App\Http\Controllers\Api\V1\Purchasing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\ListPurchaseRequestsRequest;
use App\Http\Requests\Purchasing\PurchaseRequestReasonRequest;
use App\Http\Requests\Purchasing\StorePurchaseRequestRequest;
use App\Http\Requests\Purchasing\UpdatePurchaseRequestRequest;
use App\Http\Resources\Purchasing\PurchaseRequestResource;
use App\Services\Purchasing\PurchaseRequestService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseRequestController extends Controller
{
    public function index(
        ListPurchaseRequestsRequest $request,
        PurchaseRequestService $service
    ): JsonResponse {
        $validated =
            $request->validated();

        $paginator =
            $service->paginate(
                $validated['search']
                    ?? null,

                $validated['status']
                    ?? null,

                $validated['per_page']
                    ?? 20
            );

        $data =
            collect(
                $paginator->items()
            )
                ->map(
                    fn ($purchaseRequest) =>
                        (
                            new PurchaseRequestResource(
                                $purchaseRequest
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
        StorePurchaseRequestRequest $request,
        PurchaseRequestService $service
    ): JsonResponse {
        $purchaseRequest =
            $service->createDraft(
                $request->validated()
            );

        return ApiResponse::success(
            $request,
            (
                new PurchaseRequestResource(
                    $purchaseRequest
                )
            )->resolve($request),
            201,
            'Permintaan Pembelian berhasil dibuat.'
        );
    }

    public function show(
        Request $request,
        string $purchaseRequestId,
        PurchaseRequestService $service
    ): JsonResponse {
        return ApiResponse::success(
            $request,
            (
                new PurchaseRequestResource(
                    $service->findOrFail(
                        $purchaseRequestId
                    )
                )
            )->resolve($request)
        );
    }

    public function update(
        UpdatePurchaseRequestRequest $request,
        string $purchaseRequestId,
        PurchaseRequestService $service
    ): JsonResponse {
        $purchaseRequest =
            $service->updateDraft(
                $purchaseRequestId,
                $request->validated()
            );

        return ApiResponse::success(
            $request,
            (
                new PurchaseRequestResource(
                    $purchaseRequest
                )
            )->resolve($request),
            200,
            'Draf Permintaan Pembelian berhasil diperbarui.'
        );
    }

    public function submit(
        Request $request,
        string $purchaseRequestId,
        PurchaseRequestService $service
    ): JsonResponse {
        return ApiResponse::success(
            $request,
            (
                new PurchaseRequestResource(
                    $service->submit(
                        $purchaseRequestId
                    )
                )
            )->resolve($request),
            200,
            'Permintaan Pembelian berhasil diajukan.'
        );
    }

    public function approve(
        Request $request,
        string $purchaseRequestId,
        PurchaseRequestService $service
    ): JsonResponse {
        return ApiResponse::success(
            $request,
            (
                new PurchaseRequestResource(
                    $service->approve(
                        $purchaseRequestId
                    )
                )
            )->resolve($request),
            200,
            'Permintaan Pembelian berhasil disetujui.'
        );
    }

    public function reject(
        PurchaseRequestReasonRequest $request,
        string $purchaseRequestId,
        PurchaseRequestService $service
    ): JsonResponse {
        return ApiResponse::success(
            $request,
            (
                new PurchaseRequestResource(
                    $service->reject(
                        $purchaseRequestId,
                        $request->validated(
                            'reason'
                        )
                    )
                )
            )->resolve($request),
            200,
            'Permintaan Pembelian berhasil ditolak.'
        );
    }

    public function revise(
        Request $request,
        string $purchaseRequestId,
        PurchaseRequestService $service
    ): JsonResponse {
        return ApiResponse::success(
            $request,
            (
                new PurchaseRequestResource(
                    $service->revise(
                        $purchaseRequestId
                    )
                )
            )->resolve($request),
            200,
            'Permintaan Pembelian dikembalikan ke Draf.'
        );
    }

    public function cancel(
        PurchaseRequestReasonRequest $request,
        string $purchaseRequestId,
        PurchaseRequestService $service
    ): JsonResponse {
        return ApiResponse::success(
            $request,
            (
                new PurchaseRequestResource(
                    $service->cancel(
                        $purchaseRequestId,
                        $request->validated(
                            'reason'
                        )
                    )
                )
            )->resolve($request),
            200,
            'Permintaan Pembelian berhasil dibatalkan.'
        );
    }
}
