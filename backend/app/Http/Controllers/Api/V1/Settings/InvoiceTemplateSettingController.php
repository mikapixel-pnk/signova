<?php

namespace App\Http\Controllers\Api\V1\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PreviewInvoiceTemplateRequest;
use App\Http\Requests\Settings\UpdateInvoiceTemplateSettingRequest;
use App\Services\Invoice\Document\InvoiceTemplateRegistry;
use App\Services\Settings\InvoiceTemplatePreviewService;
use App\Services\Settings\InvoiceTemplateSettingService;
use Illuminate\Validation\ValidationException;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InvoiceTemplateSettingController extends Controller
{
    public function index(
        Request $request,
        InvoiceTemplateSettingService $service
    ): JsonResponse {
        return ApiResponse::success(
            $request,
            $service->catalog()
        );
    }

    public function preview(
        PreviewInvoiceTemplateRequest $request,
        string $templateKey,
        InvoiceTemplateRegistry $registry,
        InvoiceTemplatePreviewService $previewService
    ): Response {
        abort_unless(
            $registry->has(
                $templateKey
            ),
            404
        );

        $data =
            $request->validated();

        $paletteKey =
            $data['palette']
            ?? null;

        if (
            $paletteKey !== null
            && ! $registry->supportsPalette(
                $templateKey,
                $paletteKey
            )
        ) {
            throw ValidationException::withMessages([
                'palette' => [
                    'Palet tidak tersedia untuk template ini.',
                ],
            ]);
        }

        return response(
            $previewService->render(
                $templateKey,
                $paletteKey
            ),
            200,
            [
                'Content-Type' =>
                    'text/html; charset=UTF-8',

                'Cache-Control' =>
                    'private, no-store',

                'X-Content-Type-Options' =>
                    'nosniff',
            ]
        );
    }

    public function update(
        UpdateInvoiceTemplateSettingRequest $request,
        InvoiceTemplateSettingService $service
    ): JsonResponse {
        $data =
            $request->validated();

        $service->update(
            $data['invoice_template_key'],
            $data['invoice_palette_key']
                ?? null
        );

        return ApiResponse::success(
            $request,
            $service->catalog(),
            200,
            'Template invoice berhasil diperbarui.'
        );
    }
}
