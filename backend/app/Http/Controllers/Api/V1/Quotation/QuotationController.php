<?php

namespace App\Http\Controllers\Api\V1\Quotation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quotation\ListQuotationsRequest;
use App\Http\Requests\Quotation\StoreQuotationRequest;
use App\Http\Resources\Quotation\QuotationResource;
use App\Services\Quotation\QuotationService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuotationController extends Controller
{
    public function index(
        ListQuotationsRequest $request,
        QuotationService $service
    ): JsonResponse {
        $data = $request->validated();

        $paginator = $service->paginate(
            $data['search'] ?? null,
            $data['status'] ?? null,
            $data['customer_id'] ?? null,
            $data['per_page'] ?? 20
        );

        $items = collect(
            $paginator->items()
        )
            ->map(
                fn ($quotation) =>
                    (new QuotationResource(
                        $quotation
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
        StoreQuotationRequest $request,
        QuotationService $service
    ): JsonResponse {
        $data = $request->validated();

        $quotation = $service->createDraft(
            [
                'quotation_number' =>
                    $data['quotation_number'],

                'customer_id' =>
                    $data['customer_id'],

                'valid_until' =>
                    $data['valid_until']
                    ?? null,
            ],
            [
                'currency' =>
                    $data['currency']
                    ?? 'IDR',

                'terms' =>
                    $data['terms']
                    ?? null,

                'notes' =>
                    $data['notes']
                    ?? null,
            ],
            $data['items']
        );

        return ApiResponse::success(
            $request,
            (new QuotationResource(
                $quotation
            ))->resolve($request),
            201,
            'Penawaran berhasil dibuat.'
        );
    }

    public function show(
        Request $request,
        string $quotationId,
        QuotationService $service
    ): JsonResponse {
        $quotation =
            $service->findOrFail(
                $quotationId
            );

        return ApiResponse::success(
            $request,
            (new QuotationResource(
                $quotation
            ))->resolve($request)
        );
    }
}
