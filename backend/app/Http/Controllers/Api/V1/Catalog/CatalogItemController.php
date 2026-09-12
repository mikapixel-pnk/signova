<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ListCatalogItemsRequest;
use App\Http\Requests\Catalog\StoreCatalogItemRequest;
use App\Http\Requests\Catalog\UpdateCatalogItemRequest;
use App\Http\Resources\Catalog\CatalogItemResource;
use App\Services\Catalog\CatalogService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogItemController extends Controller
{
    public function index(
        ListCatalogItemsRequest $request,
        CatalogService $service
    ): JsonResponse {
        $data = $request->validated();

        $paginator = $service->paginateItems(
            $data['search'] ?? null,
            $data['status'] ?? null,
            $data['type'] ?? null,
            $data['pricing_method'] ?? null
        );

        $items = collect(
            $paginator->items()
        )
            ->map(
                fn ($item) =>
                    (new CatalogItemResource(
                        $item
                    ))->resolve($request)
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
        StoreCatalogItemRequest $request,
        CatalogService $service
    ): JsonResponse {
        $item = $service->createItem(
            $request->validated()
        );

        return ApiResponse::success(
            $request,
            (new CatalogItemResource(
                $item
            ))->resolve($request),
            201,
            'Barang atau jasa berhasil dibuat.'
        );
    }

    public function show(
        Request $request,
        string $itemId,
        CatalogService $service
    ): JsonResponse {
        $item = $service->findItemOrFail(
            $itemId
        );

        return ApiResponse::success(
            $request,
            (new CatalogItemResource(
                $item
            ))->resolve($request)
        );
    }

    public function update(
        UpdateCatalogItemRequest $request,
        string $itemId,
        CatalogService $service
    ): JsonResponse {
        $item = $service->findItemOrFail(
            $itemId
        );

        $item = $service->updateItem(
            $item,
            $request->validated()
        );

        return ApiResponse::success(
            $request,
            (new CatalogItemResource(
                $item
            ))->resolve($request),
            200,
            'Barang atau jasa berhasil diperbarui.'
        );
    }
}
