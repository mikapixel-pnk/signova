<?php

namespace App\Http\Controllers\Api\V1\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateDocumentSettingRequest;
use App\Http\Requests\Settings\UploadDocumentSignatureRequest;
use App\Http\Resources\Settings\DocumentSettingResource;
use App\Services\File\FileService;
use App\Services\Settings\DocumentSettingService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentSettingController extends Controller
{
    public function show(
        Request $request,
        DocumentSettingService $service
    ): JsonResponse {
        $settings =
            $service->get()->load(
                'signatureImage'
            );

        return ApiResponse::success(
            $request,
            (new DocumentSettingResource(
                $settings
            ))->resolve($request)
        );
    }

    public function update(
        UpdateDocumentSettingRequest $request,
        DocumentSettingService $service
    ): JsonResponse {
        $settings =
            $service->update(
                $request->validated()
            );

        return ApiResponse::success(
            $request,
            (new DocumentSettingResource(
                $settings
            ))->resolve($request),
            200,
            'Pengaturan dokumen berhasil diperbarui.'
        );
    }

    public function uploadSignature(
        UploadDocumentSignatureRequest $request,
        DocumentSettingService $service
    ): JsonResponse {
        $settings =
            $service->replaceSignature(
                $request->file(
                    'signature'
                )
            );

        return ApiResponse::success(
            $request,
            (new DocumentSettingResource(
                $settings
            ))->resolve($request),
            200,
            'Tanda tangan berhasil disimpan.'
        );
    }

    public function signature(
        DocumentSettingService $service,
        FileService $fileService
    ): StreamedResponse {
        $file =
            $service->signatureFile();

        abort_if(
            $file === null,
            404
        );

        $stream =
            $fileService->readStream(
                $file
            );

        return response()->stream(
            function () use (
                $stream
            ): void {
                fpassthru(
                    $stream
                );

                if (
                    is_resource(
                        $stream
                    )
                ) {
                    fclose(
                        $stream
                    );
                }
            },
            200,
            [
                'Content-Type' =>
                    $file->mime_type,

                'Content-Length' =>
                    (string) $file->size_bytes,

                'Content-Disposition' =>
                    'inline',

                'X-Content-Type-Options' =>
                    'nosniff',

                'Cache-Control' =>
                    'private, no-store',
            ]
        );
    }

    public function destroySignature(
        Request $request,
        DocumentSettingService $service
    ): JsonResponse {
        $settings =
            $service->removeSignature();

        return ApiResponse::success(
            $request,
            (new DocumentSettingResource(
                $settings
            ))->resolve($request),
            200,
            'Tanda tangan berhasil dihapus.'
        );
    }
}
