<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreMaterialRequest;
use App\Http\Requests\Inventory\UpdateMaterialRequest;
use App\Http\Resources\Inventory\MaterialResource;
use App\Services\Inventory\InventoryMasterService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaterialController extends Controller
{
    public function index(
        Request $request,
        InventoryMasterService $service
    ): JsonResponse {
        $data =
            $service
                ->materials()
                ->map(
                    fn ($material) =>
                        (
                            new MaterialResource(
                                $material
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
        StoreMaterialRequest $request,
        InventoryMasterService $service
    ): JsonResponse {
        $material =
            $service->createMaterial(
                $request->validated()
            );

        return ApiResponse::success(
            $request,
            (
                new MaterialResource(
                    $material
                )
            )->resolve($request),
            201,
            'Material berhasil dibuat.'
        );
    }

    public function update(
        UpdateMaterialRequest $request,
        string $materialId,
        InventoryMasterService $service
    ): JsonResponse {
        $material =
            $service->findMaterialOrFail(
                $materialId
            );

        $material =
            $service->updateMaterial(
                $material,
                $request->validated()
            );

        return ApiResponse::success(
            $request,
            (
                new MaterialResource(
                    $material
                )
            )->resolve($request),
            200,
            'Material berhasil diperbarui.'
        );
    }
}
