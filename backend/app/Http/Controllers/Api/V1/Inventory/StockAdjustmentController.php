<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ReverseStockAdjustmentRequest;
use App\Http\Requests\Inventory\StoreStockAdjustmentRequest;
use App\Http\Requests\Inventory\UpdateStockAdjustmentRequest;
use App\Http\Resources\Inventory\StockAdjustmentResource;
use App\Services\Inventory\StockAdjustmentService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockAdjustmentController extends Controller
{
    public function index(
        Request $request,
        StockAdjustmentService $service
    ): JsonResponse {
        $data =
            $service
                ->all()
                ->map(
                    fn ($adjustment) =>
                        (
                            new StockAdjustmentResource(
                                $adjustment
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
        StoreStockAdjustmentRequest $request,
        StockAdjustmentService $service
    ): JsonResponse {
        $adjustment =
            $service->createDraft(
                $request->validated()
            );

        return ApiResponse::success(
            $request,
            (
                new StockAdjustmentResource(
                    $adjustment
                )
            )->resolve($request),
            201,
            'Penyesuaian Stok berhasil dibuat.'
        );
    }

    public function show(
        Request $request,
        string $stockAdjustmentId,
        StockAdjustmentService $service
    ): JsonResponse {
        return ApiResponse::success(
            $request,
            (
                new StockAdjustmentResource(
                    $service->findOrFail(
                        $stockAdjustmentId
                    )
                )
            )->resolve($request)
        );
    }

    public function update(
        UpdateStockAdjustmentRequest $request,
        string $stockAdjustmentId,
        StockAdjustmentService $service
    ): JsonResponse {
        $adjustment =
            $service->updateDraft(
                $stockAdjustmentId,
                $request->validated()
            );

        return ApiResponse::success(
            $request,
            (
                new StockAdjustmentResource(
                    $adjustment
                )
            )->resolve($request),
            200,
            'Penyesuaian Stok berhasil diperbarui.'
        );
    }

    public function post(
        Request $request,
        string $stockAdjustmentId,
        StockAdjustmentService $service
    ): JsonResponse {
        $adjustment =
            $service->post(
                $stockAdjustmentId
            );

        return ApiResponse::success(
            $request,
            (
                new StockAdjustmentResource(
                    $adjustment
                )
            )->resolve($request),
            200,
            'Penyesuaian Stok berhasil dicatat.'
        );
    }

    public function reverse(
        ReverseStockAdjustmentRequest $request,
        string $stockAdjustmentId,
        StockAdjustmentService $service
    ): JsonResponse {
        $adjustment =
            $service->reverse(
                $stockAdjustmentId,
                $request->validated(
                    'reason'
                )
            );

        return ApiResponse::success(
            $request,
            (
                new StockAdjustmentResource(
                    $adjustment
                )
            )->resolve($request),
            200,
            'Penyesuaian Stok berhasil dikoreksi.'
        );
    }
}
