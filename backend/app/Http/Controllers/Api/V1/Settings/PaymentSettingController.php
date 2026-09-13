<?php

namespace App\Http\Controllers\Api\V1\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdatePaymentSettingRequest;
use App\Http\Requests\Settings\UploadPaymentQrRequest;
use App\Http\Resources\Settings\PaymentSettingResource;
use App\Services\File\FileService;
use App\Services\Settings\PaymentSettingService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentSettingController extends Controller
{
    public function show(
        Request $request,
        PaymentSettingService $service
    ): JsonResponse {
        $settings = $service->get();

        if ($settings->exists) {
            $settings->load(
                'staticQrFile'
            );
        }

        return ApiResponse::success(
            $request,
            (new PaymentSettingResource(
                $settings
            ))->resolve($request)
        );
    }

    public function update(
        UpdatePaymentSettingRequest $request,
        PaymentSettingService $service
    ): JsonResponse {
        $settings =
            $service->update(
                $request->validated()
            );

        return ApiResponse::success(
            $request,
            (new PaymentSettingResource(
                $settings
            ))->resolve($request),
            200,
            'Pengaturan pembayaran berhasil diperbarui.'
        );
    }

    public function uploadStaticQr(
        UploadPaymentQrRequest $request,
        PaymentSettingService $service
    ): JsonResponse {
        $settings =
            $service->replaceStaticQr(
                $request->file('qr')
            );

        return ApiResponse::success(
            $request,
            (new PaymentSettingResource(
                $settings
            ))->resolve($request),
            200,
            'QR pembayaran berhasil disimpan.'
        );
    }

    public function staticQr(
        PaymentSettingService $service,
        FileService $fileService
    ): StreamedResponse {
        $file =
            $service->staticQrFile();

        abort_if(
            $file === null,
            404
        );

        $stream =
            $fileService->readStream(
                $file
            );

        return response()->stream(
            function () use ($stream): void {
                fpassthru($stream);

                if (is_resource($stream)) {
                    fclose($stream);
                }
            },
            200,
            [
                'Content-Type' =>
                    $file->mime_type,

                'Content-Length' =>
                    (string) $file
                        ->size_bytes,

                'Content-Disposition' =>
                    'inline',

                'X-Content-Type-Options' =>
                    'nosniff',

                'Cache-Control' =>
                    'private, no-store',
            ]
        );
    }

    public function destroyStaticQr(
        Request $request,
        PaymentSettingService $service
    ): JsonResponse {
        $settings =
            $service->removeStaticQr();

        return ApiResponse::success(
            $request,
            (new PaymentSettingResource(
                $settings
            ))->resolve($request),
            200,
            'QR pembayaran berhasil dihapus.'
        );
    }
}
