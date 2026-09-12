<?php

namespace App\Http\Controllers\Api\Public\V1\Quotation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quotation\RejectPublicQuotationRequest;
use App\Http\Resources\Quotation\PublicQuotationResource;
use App\Services\Quotation\QuotationPublicLinkService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicQuotationController extends Controller
{
    public function show(
        Request $request,
        string $token,
        QuotationPublicLinkService $service
    ): JsonResponse {
        $link =
            $service->resolveByPresentedToken(
                $token
            );

        return ApiResponse::success(
            $request,
            (new PublicQuotationResource(
                $link
            ))->resolve($request)
        );
    }

    public function approve(
        Request $request,
        string $token,
        QuotationPublicLinkService $service
    ): JsonResponse {
        $link =
            $service->approve(
                $token
            );

        return ApiResponse::success(
            $request,
            (new PublicQuotationResource(
                $link
            ))->resolve($request),
            200,
            'Penawaran berhasil disetujui.'
        );
    }

    public function reject(
        RejectPublicQuotationRequest $request,
        string $token,
        QuotationPublicLinkService $service
    ): JsonResponse {
        $link =
            $service->reject(
                $token,
                $request->validated('reason')
            );

        return ApiResponse::success(
            $request,
            (new PublicQuotationResource(
                $link
            ))->resolve($request),
            200,
            'Penawaran berhasil ditolak.'
        );
    }

    public function view(
        Request $request,
        string $token,
        QuotationPublicLinkService $service
    ): JsonResponse {
        $link =
            $service->markViewed(
                $token
            );

        return ApiResponse::success(
            $request,
            (new PublicQuotationResource(
                $link
            ))->resolve($request),
            200,
            'Penawaran telah ditandai sebagai dilihat.'
        );
    }
}
