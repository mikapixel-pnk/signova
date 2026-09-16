<?php

namespace App\Http\Controllers\Api\V1\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateBusinessProfileRequest;
use App\Http\Resources\Settings\BusinessProfileResource;
use App\Services\Settings\BusinessProfileService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
