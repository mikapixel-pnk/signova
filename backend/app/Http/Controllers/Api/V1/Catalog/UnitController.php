<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
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
}
