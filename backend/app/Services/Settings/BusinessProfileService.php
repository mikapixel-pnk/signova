<?php

namespace App\Services\Settings;

use App\Models\BusinessProfile;
use App\Models\FileAsset;
use App\Services\File\FileService;
use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class BusinessProfileService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BusinessContext $businessContext
    ) {
    }

    public function get(): BusinessProfile
    {
        return BusinessProfile::query()
            ->where(
                'tenant_id',
                $this->tenantContext->tenantId()
            )
            ->where(
                'id',
                $this->businessContext->businessId()
            )
            ->firstOrFail();
    }

    public function update(
        array $data
    ): BusinessProfile {
        $business = $this->get();

        $business->fill($data);
        $business->save();

        return $business->fresh();
    }

    public function logoFile(): ?FileAsset
    {
        $business = $this->get();

        if ($business->logo_file_id === null) {
            return null;
        }

        return FileAsset::query()
            ->where(
                'tenant_id',
                $this->tenantContext->tenantId()
            )
            ->where(
                'id',
                $business->logo_file_id
            )
            ->where(
                'purpose',
                'BUSINESS_LOGO'
            )
            ->first();
    }

    public function replaceLogo(
        UploadedFile $uploadedFile,
        FileService $fileService
    ): BusinessProfile {
        $business = $this->get();
        $oldFile = $this->logoFile();

        $newFile =
            $fileService->storeBusinessLogo(
                $uploadedFile
            );

        try {
            $updatedBusiness =
                DB::transaction(
                    function () use (
                        $business,
                        $newFile
                    ): BusinessProfile {
                        $business->logo_file_id =
                            $newFile->id;

                        $business->save();

                        return $business->fresh();
                    }
                );
        } catch (\Throwable $exception) {
            $fileService->deleteObject(
                $newFile
            );

            $newFile->delete();

            throw $exception;
        }

        if ($oldFile !== null) {
            $fileService->deleteObject(
                $oldFile
            );

            $oldFile->delete();
        }

        return $updatedBusiness;
    }

    public function removeLogo(
        FileService $fileService
    ): BusinessProfile {
        $business = $this->get();
        $file = $this->logoFile();

        return DB::transaction(
            function () use (
                $business,
                $file,
                $fileService
            ): BusinessProfile {
                $business->logo_file_id =
                    null;

                $business->save();

                if ($file !== null) {
                    $fileService->deleteObject(
                        $file
                    );

                    $file->delete();
                }

                return $business->fresh();
            }
        );
    }
}
