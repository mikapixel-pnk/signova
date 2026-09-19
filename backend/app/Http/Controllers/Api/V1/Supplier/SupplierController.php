<?php

namespace App\Http\Controllers\Api\V1\Supplier;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supplier\ListSuppliersRequest;
use App\Http\Requests\Supplier\StoreSupplierRequest;
use App\Http\Requests\Supplier\UpdateSupplierRequest;
use App\Http\Resources\Supplier\SupplierResource;
use App\Services\Supplier\SupplierService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(
        ListSuppliersRequest $request,
        SupplierService $service
    ): JsonResponse {
        $validated = $request->validated();

        $paginator = $service->paginate(
            $validated['search'] ?? null,
            $validated['status'] ?? null
        );

        $data = collect(
            $paginator->items()
        )
            ->map(
                fn ($supplier) =>
                    (new SupplierResource(
                        $supplier
                    ))->resolve($request)
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
        StoreSupplierRequest $request,
        SupplierService $service
    ): JsonResponse {
        $supplier = $service->create(
            $request->validated()
        );

        return ApiResponse::success(
            $request,
            (new SupplierResource(
                $supplier
            ))->resolve($request),
            201,
            'Pemasok berhasil dibuat.'
        );
    }

    public function show(
        Request $request,
        string $supplierId,
        SupplierService $service
    ): JsonResponse {
        $supplier = $service->findOrFail(
            $supplierId
        );

        return ApiResponse::success(
            $request,
            (new SupplierResource(
                $supplier
            ))->resolve($request)
        );
    }

    public function update(
        UpdateSupplierRequest $request,
        string $supplierId,
        SupplierService $service
    ): JsonResponse {
        $supplier = $service->findOrFail(
            $supplierId
        );

        $supplier = $service->update(
            $supplier,
            $request->validated()
        );

        return ApiResponse::success(
            $request,
            (new SupplierResource(
                $supplier
            ))->resolve($request),
            200,
            'Pemasok berhasil diperbarui.'
        );
    }
}
