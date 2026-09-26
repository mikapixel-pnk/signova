<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreInventoryCategoryRequest;
use App\Http\Requests\Inventory\UpdateInventoryCategoryRequest;
use App\Http\Resources\Inventory\InventoryCategoryResource;
use App\Services\Inventory\InventoryMasterService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryCategoryController extends Controller
{
    public function index(
        Request $request,
        InventoryMasterService $service
    ): JsonResponse {
        $data =
            $service
                ->categories()
                ->map(
                    fn ($category) =>
                        (
                            new InventoryCategoryResource(
                                $category
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
        StoreInventoryCategoryRequest $request,
        InventoryMasterService $service
    ): JsonResponse {
        $category =
            $service->createCategory(
                $request->validated()
            );

        return ApiResponse::success(
            $request,
            (
                new InventoryCategoryResource(
                    $category
                )
            )->resolve($request),
            201,
            'Kategori persediaan berhasil dibuat.'
        );
    }

    public function update(
        UpdateInventoryCategoryRequest $request,
        string $inventoryCategoryId,
        InventoryMasterService $service
    ): JsonResponse {
        $category =
            $service->findCategoryOrFail(
                $inventoryCategoryId
            );

        $category =
            $service->updateCategory(
                $category,
                $request->validated()
            );

        return ApiResponse::success(
            $request,
            (
                new InventoryCategoryResource(
                    $category
                )
            )->resolve($request),
            200,
            'Kategori persediaan berhasil diperbarui.'
        );
    }
}
