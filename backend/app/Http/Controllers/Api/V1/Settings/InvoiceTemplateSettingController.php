<?php

namespace App\Http\Controllers\Api\V1\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateInvoiceTemplateSettingRequest;
use App\Services\Settings\InvoiceTemplateSettingService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
