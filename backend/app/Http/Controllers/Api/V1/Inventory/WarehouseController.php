<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreWarehouseRequest;
use App\Http\Requests\Inventory\UpdateWarehouseRequest;
use App\Http\Resources\Inventory\WarehouseResource;
use App\Services\Inventory\InventoryMasterService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function index(
        Request $request,
        InventoryMasterService $service
    ): JsonResponse {
        $data =
            $service
                ->warehouses()
                ->map(
                    fn ($warehouse) =>
                        (
                            new WarehouseResource(
                                $warehouse
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
        StoreWarehouseRequest $request,
        InventoryMasterService $service
    ): JsonResponse {
        $warehouse =
            $service->createWarehouse(
                $request->validated()
            );

        return ApiResponse::success(
            $request,
            (
                new WarehouseResource(
                    $warehouse
                )
            )->resolve($request),
            201,
            'Gudang berhasil dibuat.'
        );
    }

    public function update(
        UpdateWarehouseRequest $request,
        string $warehouseId,
        InventoryMasterService $service
    ): JsonResponse {
        $warehouse =
            $service->findWarehouseOrFail(
                $warehouseId
            );

        $warehouse =
            $service->updateWarehouse(
                $warehouse,
                $request->validated()
            );

        return ApiResponse::success(
            $request,
            (
                new WarehouseResource(
                    $warehouse
                )
            )->resolve($request),
            200,
            'Gudang berhasil diperbarui.'
        );
    }
}
