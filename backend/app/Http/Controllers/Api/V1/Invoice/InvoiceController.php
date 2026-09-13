<?php

namespace App\Http\Controllers\Api\V1\Invoice;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoice\ListInvoicesRequest;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Invoice\VoidInvoiceRequest;
use App\Http\Resources\Invoice\InvoiceResource;
use App\Services\Invoice\InvoicePdfService;
use App\Services\Invoice\InvoiceService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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

    public function store(
        StoreInvoiceRequest $request,
        InvoiceService $service
    ): JsonResponse {
        $data =
            $request->validated();

        $invoice =
            $service->createDraft(
                $data['customer_id'],
                $data['items'],
                $data['due_at']
                    ?? null,
                $data['notes']
                    ?? null
            );

        return ApiResponse::success(
            $request,
            (new InvoiceResource(
                $invoice
            ))->resolve($request),
            201,
            'Tagihan berhasil dibuat.'
        );
    }

    public function issue(
        Request $request,
        string $invoiceId,
        InvoiceService $service
    ): JsonResponse {
        $invoice =
            $service->issue(
                $invoiceId
            );

        return ApiResponse::success(
            $request,
            (new InvoiceResource(
                $invoice
            ))->resolve($request),
            200,
            'Tagihan berhasil diterbitkan.'
        );
    }

    public function void(
        VoidInvoiceRequest $request,
        string $invoiceId,
        InvoiceService $service
    ): JsonResponse {
        $invoice =
            $service->void(
                $invoiceId,
                $request->validated('reason')
            );

        return ApiResponse::success(
            $request,
            (new InvoiceResource(
                $invoice
            ))->resolve($request),
            200,
            'Tagihan berhasil dibatalkan.'
        );
    }

    public function pdf(
        string $invoiceId,
        InvoicePdfService $service
    ): Response {
        $document =
            $service->document(
                $invoiceId
            );

        return response(
            $document['content'],
            200,
            [
                'Content-Type' =>
                    'application/pdf',

                'Content-Disposition' =>
                    'attachment; filename="'
                    . $document['filename']
                    . '"',

                'X-Content-Type-Options' =>
                    'nosniff',

                'Cache-Control' =>
                    'private, no-store',
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
