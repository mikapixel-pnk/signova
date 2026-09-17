<?php

namespace App\Http\Controllers\Api\V1\Invoice;

use App\Http\Controllers\Controller;
use App\Services\Invoice\InvoicePublicLinkService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoicePublicLinkController extends Controller
{
    public function store(
        Request $request,
        string $invoiceId,
        InvoicePublicLinkService $service
    ): JsonResponse {
        $result =
            $service->issueForInvoice(
                $invoiceId
            );

        return ApiResponse::success(
            $request,
            [
                'public_url' =>
                    $result[
                        'public_url'
                    ],

                'expires_at' =>
                    $result[
                        'link'
                    ]->expires_at
                        ?->toIso8601String(),
            ],
            201,
            'Link tagihan berhasil dibuat.'
        );
    }
}
