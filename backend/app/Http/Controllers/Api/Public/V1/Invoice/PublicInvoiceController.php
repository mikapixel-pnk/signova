<?php

namespace App\Http\Controllers\Api\Public\V1\Invoice;

use App\Http\Controllers\Controller;
use App\Http\Resources\Invoice\PublicInvoiceResource;
use App\Services\Invoice\InvoicePublicLinkService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicInvoiceController extends Controller
{
    public function show(
        Request $request,
        string $token,
        InvoicePublicLinkService $service
    ): JsonResponse {
        $link =
            $service
                ->resolveByPresentedToken(
                    $token
                );

        return ApiResponse::success(
            $request,
            (new PublicInvoiceResource(
                $link
            ))->resolve(
                $request
            )
        );
    }
}
