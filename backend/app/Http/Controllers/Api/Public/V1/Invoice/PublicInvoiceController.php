<?php

namespace App\Http\Controllers\Api\Public\V1\Invoice;

use App\Http\Controllers\Controller;
use App\Http\Resources\Invoice\PublicInvoiceResource;
use App\Services\Invoice\InvoicePdfService;
use App\Services\Invoice\InvoicePublicLinkService;
use App\Services\Invoice\PublicInvoicePresentationService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PublicInvoiceController extends Controller
{
    public function show(
        Request $request,
        string $token,
        InvoicePublicLinkService $service,
        PublicInvoicePresentationService $presentation
    ): JsonResponse {
        $link =
            $service
                ->resolveByPresentedToken(
                    $token
                );

        return ApiResponse::success(
            $request,
            (new PublicInvoiceResource(
                $presentation->build(
                    $link
                )
            ))->resolve(
                $request
            )
        );
    }

    public function pdf(
        string $token,
        InvoicePublicLinkService $service,
        InvoicePdfService $pdfService
    ): Response {
        /*
         * Token adalah satu-satunya public credential.
         * tenant/business/invoice selalu di-resolve server-side.
         */
        $link =
            $service
                ->resolveByPresentedToken(
                    $token
                );

        $document =
            $pdfService
                ->documentForInvoice(
                    $link->invoice
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

                /*
                 * PDF harus selalu merefleksikan
                 * status invoice terakhir.
                 */
                'Cache-Control' =>
                    'private, no-store, no-cache, must-revalidate, max-age=0',

                'Pragma' =>
                    'no-cache',

                'X-Content-Type-Options' =>
                    'nosniff',

                'Referrer-Policy' =>
                    'no-referrer',
            ]
        );
    }
}
