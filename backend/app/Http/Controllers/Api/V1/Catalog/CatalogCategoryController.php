<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreCatalogCategoryRequest;
use App\Http\Requests\Catalog\UpdateCatalogCategoryRequest;
use App\Http\Resources\Catalog\CatalogCategoryResource;
use App\Services\Catalog\CatalogService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogCategoryController extends Controller
{
    public function index(
        Request $request,
        CatalogService $service
    ): JsonResponse {
        $data = collect(
            $service->categories()
        )
            ->map(
                fn ($category) =>
                    (new CatalogCategoryResource(
                        $category
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
        StoreCatalogCategoryRequest $request,
        CatalogService $service
    ): JsonResponse {
        $category = $service->createCategory(
            $request->validated()
        );

        return ApiResponse::success(
            $request,
            (new CatalogCategoryResource(
                $category
            ))->resolve($request),
            201,
            'Kategori berhasil dibuat.'
        );
    }

    public function update(
        UpdateCatalogCategoryRequest $request,
        string $categoryId,
        CatalogService $service
    ): JsonResponse {
        $category =
            $service->findCategoryOrFail(
                $categoryId
            );

        $category =
            $service->updateCategory(
                $category,
                $request->validated()
            );

        return ApiResponse::success(
            $request,
            (new CatalogCategoryResource(
                $category
            ))->resolve($request),
            200,
            'Kategori berhasil diperbarui.'
        );
    }
}
