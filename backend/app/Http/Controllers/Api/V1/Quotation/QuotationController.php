<?php

namespace App\Http\Controllers\Api\V1\Quotation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quotation\CancelQuotationRequest;
use App\Http\Requests\Quotation\CreateInvoiceFromQuotationRequest;
use App\Http\Requests\Quotation\CreateQuotationRevisionRequest;
use App\Http\Requests\Quotation\ListQuotationsRequest;
use App\Http\Requests\Quotation\StoreQuotationRequest;
use App\Http\Requests\Quotation\UpdateQuotationRequest;
use App\Http\Resources\Invoice\InvoiceResource;
use App\Http\Resources\Quotation\QuotationResource;
use App\Services\Invoice\QuotationToInvoiceService;
use App\Services\Quotation\QuotationPdfService;
use App\Services\Quotation\QuotationService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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

    public function createInvoice(
        CreateInvoiceFromQuotationRequest $request,
        string $quotationId,
        QuotationToInvoiceService $service
    ): JsonResponse {
        $data = $request->validated();

        $invoice = $service->convert(
            $quotationId,
            $data['due_at'] ?? null
        );

        $status =
            $invoice->wasRecentlyCreated
                ? 201
                : 200;

        return ApiResponse::success(
            $request,
            (new InvoiceResource(
                $invoice
            ))->resolve($request),
            $status,
            $invoice->wasRecentlyCreated
                ? 'Tagihan berhasil dibuat dari Penawaran.'
                : 'Tagihan dari Penawaran ini sudah tersedia.'
        );
    }

    public function send(
        Request $request,
        string $quotationId,
        QuotationService $service
    ): JsonResponse {
        $quotation = $service->send(
            $quotationId
        );

        return ApiResponse::success(
            $request,
            (new QuotationResource(
                $quotation
            ))->resolve($request),
            200,
            'Penawaran berhasil dikirim.'
        );
    }

    public function cancel(
        CancelQuotationRequest $request,
        string $quotationId,
        QuotationService $service
    ): JsonResponse {
        $quotation = $service->cancel(
            $quotationId,
            $request->validated('reason')
        );

        return ApiResponse::success(
            $request,
            (new QuotationResource(
                $quotation
            ))->resolve($request),
            200,
            'Penawaran berhasil dibatalkan.'
        );
    }

    public function storeVersion(
        CreateQuotationRevisionRequest $request,
        string $quotationId,
        QuotationService $service
    ): JsonResponse {
        $data = $request->validated();

        $quotation = $service->createRevision(
            $quotationId,
            [
                'currency' =>
                    $data['currency'] ?? 'IDR',

                'terms' =>
                    $data['terms'] ?? null,

                'notes' =>
                    $data['notes'] ?? null,
            ],
            $data['items']
        );

        return ApiResponse::success(
            $request,
            (new QuotationResource(
                $quotation
            ))->resolve($request),
            201,
            'Revisi penawaran berhasil dibuat.'
        );
    }

    public function update(
        UpdateQuotationRequest $request,
        string $quotationId,
        QuotationService $service
    ): JsonResponse {
        $quotation = $service->updateDraftHeader(
            $quotationId,
            $request->validated()
        );

        return ApiResponse::success(
            $request,
            (new QuotationResource(
                $quotation
            ))->resolve($request),
            200,
            'Penawaran berhasil diperbarui.'
        );
    }

    public function pdf(
        string $quotationId,
        QuotationPdfService $service
    ): Response {
        $document =
            $service->document(
                $quotationId
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
