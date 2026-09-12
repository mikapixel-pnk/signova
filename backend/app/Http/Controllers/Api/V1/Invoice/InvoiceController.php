<?php

namespace App\Http\Controllers\Api\V1\Invoice;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoice\ListInvoicesRequest;
use App\Http\Resources\Invoice\InvoiceResource;
use App\Services\Invoice\InvoiceService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index(
        ListInvoicesRequest $request,
        InvoiceService $service
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
                fn ($invoice) =>
                    (new InvoiceResource(
                        $invoice
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

    public function show(
        Request $request,
        string $invoiceId,
        InvoiceService $service
    ): JsonResponse {
        $invoice =
            $service->findOrFail(
                $invoiceId
            );

        return ApiResponse::success(
            $request,
            (new InvoiceResource(
                $invoice
            ))->resolve($request)
        );
    }
}
