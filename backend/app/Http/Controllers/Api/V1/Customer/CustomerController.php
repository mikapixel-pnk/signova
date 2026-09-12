<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\ListCustomersRequest;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Resources\Customer\CustomerResource;
use App\Services\Customer\CustomerService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(
        ListCustomersRequest $request,
        CustomerService $service
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
                fn ($customer) =>
                    (new CustomerResource(
                        $customer
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
        StoreCustomerRequest $request,
        CustomerService $service
    ): JsonResponse {
        $customer = $service->create(
            $request->validated()
        );

        return ApiResponse::success(
            $request,
            (new CustomerResource(
                $customer
            ))->resolve($request),
            201,
            'Pelanggan berhasil dibuat.'
        );
    }

    public function show(
        Request $request,
        string $customerId,
        CustomerService $service
    ): JsonResponse {
        $customer = $service->findOrFail(
            $customerId
        );

        return ApiResponse::success(
            $request,
            (new CustomerResource(
                $customer
            ))->resolve($request)
        );
    }

    public function update(
        UpdateCustomerRequest $request,
        string $customerId,
        CustomerService $service
    ): JsonResponse {
        $customer = $service->findOrFail(
            $customerId
        );

        $customer = $service->update(
            $customer,
            $request->validated()
        );

        return ApiResponse::success(
            $request,
            (new CustomerResource(
                $customer
            ))->resolve($request),
            200,
            'Pelanggan berhasil diperbarui.'
        );
    }
}
