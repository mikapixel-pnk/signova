<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ReverseGoodsReceiptRequest;
use App\Http\Requests\Inventory\StoreGoodsReceiptRequest;
use App\Http\Requests\Inventory\UpdateGoodsReceiptRequest;
use App\Http\Resources\Inventory\GoodsReceiptResource;
use App\Services\Inventory\GoodsReceiptService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoodsReceiptController extends Controller
{
    public function index(
        Request $request,
        GoodsReceiptService $service
    ): JsonResponse {
        $data =
            $service
                ->all(
                    $request->query(
                        'purchase_order_id'
                    )
                )
                ->map(
                    fn ($receipt) =>
                        (
                            new GoodsReceiptResource(
                                $receipt
                            )
                        )->resolve($request)
                )
                ->values()
                ->all();

        return ApiResponse::success(
            $request,
            $data
        );
    }

    public function store(
        StoreGoodsReceiptRequest $request,
        GoodsReceiptService $service
    ): JsonResponse {
        $receipt =
            $service->createDraft(
                $request->validated()
            );

        return ApiResponse::success(
            $request,
            (
                new GoodsReceiptResource(
                    $receipt
                )
            )->resolve($request),
            201,
            'Penerimaan berhasil dibuat.'
        );
    }

    public function show(
        Request $request,
        string $goodsReceiptId,
        GoodsReceiptService $service
    ): JsonResponse {
        return ApiResponse::success(
            $request,
            (
                new GoodsReceiptResource(
                    $service->findOrFail(
                        $goodsReceiptId
                    )
                )
            )->resolve($request)
        );
    }

    public function update(
        UpdateGoodsReceiptRequest $request,
        string $goodsReceiptId,
        GoodsReceiptService $service
    ): JsonResponse {
        $receipt =
            $service->updateDraft(
                $goodsReceiptId,
                $request->validated()
            );

        return ApiResponse::success(
            $request,
            (
                new GoodsReceiptResource(
                    $receipt
                )
            )->resolve($request),
            200,
            'Penerimaan berhasil diperbarui.'
        );
    }

    public function post(
        Request $request,
        string $goodsReceiptId,
        GoodsReceiptService $service
    ): JsonResponse {
        $receipt =
            $service->post(
                $goodsReceiptId
            );

        return ApiResponse::success(
            $request,
            (
                new GoodsReceiptResource(
                    $receipt
                )
            )->resolve($request),
            200,
            'Penerimaan berhasil dicatat.'
        );
    }

    public function reverse(
        ReverseGoodsReceiptRequest $request,
        string $goodsReceiptId,
        GoodsReceiptService $service
    ): JsonResponse {
        $receipt =
            $service->reverse(
                $goodsReceiptId,
                $request->validated(
                    'reason'
                )
            );

        return ApiResponse::success(
            $request,
            (
                new GoodsReceiptResource(
                    $receipt
                )
            )->resolve($request),
            200,
            'Penerimaan berhasil dikoreksi.'
        );
    }
}
