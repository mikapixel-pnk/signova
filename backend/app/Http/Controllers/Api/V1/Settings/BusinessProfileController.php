<?php

namespace App\Http\Controllers\Api\V1\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateBusinessProfileRequest;
use App\Http\Requests\Settings\UploadBusinessLogoRequest;
use App\Http\Resources\Settings\BusinessProfileResource;
use App\Services\File\FileService;
use App\Services\Settings\BusinessProfileService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BusinessProfileController extends Controller
{
    public function show(
        Request $request,
        BusinessProfileService $service
    ): JsonResponse {
        return ApiResponse::success(
            $request,
            (new BusinessProfileResource(
                $service->get()
            ))->resolve($request)
        );
    }

    public function update(
        UpdateBusinessProfileRequest $request,
        BusinessProfileService $service
    ): JsonResponse {
        $business =
            $service->update(
                $request->validated()
            );

        return ApiResponse::success(
            $request,
            (new BusinessProfileResource(
                $business
            ))->resolve($request),
            200,
            'Profil usaha berhasil diperbarui.'
        );
    }

    public function uploadLogo(
        UploadBusinessLogoRequest $request,
        BusinessProfileService $service,
        FileService $fileService
    ): JsonResponse {
        $business =
            $service->replaceLogo(
                $request->file('logo'),
                $fileService
            );

        return ApiResponse::success(
            $request,
            (new BusinessProfileResource(
                $business
            ))->resolve($request),
            200,
            'Logo usaha berhasil disimpan.'
        );
    }

    public function logo(
        BusinessProfileService $service,
        FileService $fileService
    ): StreamedResponse {
        $file =
            $service->logoFile();

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

    public function destroyLogo(
        Request $request,
        BusinessProfileService $service,
        FileService $fileService
    ): JsonResponse {
        $business =
            $service->removeLogo(
                $fileService
            );

        return ApiResponse::success(
            $request,
            (new BusinessProfileResource(
                $business
            ))->resolve($request),
            200,
            'Logo usaha berhasil dihapus.'
        );
    }
}
