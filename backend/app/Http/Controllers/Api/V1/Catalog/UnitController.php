<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreUnitRequest;
use App\Http\Requests\Catalog\UpdateUnitRequest;
use App\Http\Resources\Catalog\UnitResource;
use App\Services\Catalog\CatalogService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function index(
        Request $request,
        CatalogService $service
    ): JsonResponse {
        $data = collect(
            $service->units()
        )
            ->map(
                fn ($unit) =>
                    (new UnitResource(
                        $unit
                    ))->resolve($request)
            )
            ->values()
            ->all();

        return ApiResponse::success(
            $request,
            $data
        );
    }

    public function store(
        StoreUnitRequest $request,
        CatalogService $service
    ): JsonResponse {
        $unit = $service->createUnit(
            $request->validated()
        );

        return ApiResponse::success(
            $request,
            (new UnitResource(
                $unit
            ))->resolve($request),
            201,
            'Satuan berhasil dibuat.'
        );
    }

    public function update(
        UpdateUnitRequest $request,
        string $unitId,
        CatalogService $service
    ): JsonResponse {
        $unit =
            $service->findUnitOrFail(
                $unitId
            );

        $unit =
            $service->updateUnit(
                $unit,
                $request->validated()
            );

        return ApiResponse::success(
            $request,
            (new UnitResource(
                $unit
            ))->resolve($request),
            200,
            'Satuan berhasil diperbarui.'
        );
    }
}
