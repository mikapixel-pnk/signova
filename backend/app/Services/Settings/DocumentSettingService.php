<?php

namespace App\Services\Settings;

use App\Models\FileAsset;
use App\Models\TenantDocumentSetting;
use App\Services\File\FileService;
use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class DocumentSettingService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BusinessContext $businessContext,
        private readonly FileService $fileService
    ) {
    }

    public function get(): TenantDocumentSetting
    {
        $tenantId =
            $this->tenantContext->tenantId();

        $settings =
            TenantDocumentSetting::query()
                ->firstOrCreate(
                    [
                        'tenant_id' =>
                            $tenantId,

                        'business_id' =>
                            $this->businessContext
                                ->businessId(),
                    ],
                    $this->defaultAttributes()
                );

        return $settings->loadMissing([
            'business',
            'signatureImage',
        ]);
    }

    public function update(
        array $data
    ): TenantDocumentSetting {
        $settings =
            $this->get();

        $settings->fill(
            $data
        );

        $settings->save();

        return $settings->fresh([
            'signatureImage',
        ]);
    }

    public function replaceSignature(
        UploadedFile $uploadedFile
    ): TenantDocumentSetting {
        $newFile =
            $this->fileService->storeSignature(
                $uploadedFile
            );

        $oldFile = null;

        try {
            DB::transaction(
                function () use (
                    $newFile,
                    &$oldFile
                ): void {
                    $tenantId =
                        $this->tenantContext
                            ->tenantId();

                    $settings =
                        TenantDocumentSetting::query()
                            ->where(
                                'tenant_id',
                                $tenantId
                            )
                            ->where(
                                'business_id',
                                $this->businessContext
                                    ->businessId()
                            )
                            ->lockForUpdate()
                            ->first();

                    if ($settings === null) {
                        $settings =
                            TenantDocumentSetting::query()
                                ->create(
                                    array_merge(
                                        [
                                            'tenant_id' =>
                                                $tenantId,

                                            'business_id' =>
                                                $this->businessContext
                                                    ->businessId(),
                                        ],
                                        $this->defaultAttributes()
                                    )
                                );
                    }

                    if (
                        $settings
                            ->signature_image_file_id
                    ) {
                        $oldFile =
                            FileAsset::query()
                                ->where(
                                    'tenant_id',
                                    $tenantId
                                )
                                ->where(
                                    'id',
                                    $settings
                                        ->signature_image_file_id
                                )
                                ->first();
                    }

                    $settings
                        ->signature_image_file_id =
                        $newFile->id;

                    $settings->save();
                }
            );
        } catch (\Throwable $exception) {
            $this->fileService->deleteObject(
                $newFile
            );

            $newFile->delete();

            throw $exception;
        }

        if ($oldFile !== null) {
            $this->fileService->deleteObject(
                $oldFile
            );

            $oldFile->delete();
        }

        return $this->get()->load(
            'signatureImage'
        );
    }

    public function removeSignature(): TenantDocumentSetting
    {
        $tenantId =
            $this->tenantContext->tenantId();

        $oldFile = null;

        DB::transaction(
            function () use (
                $tenantId,
                &$oldFile
            ): void {
                $settings =
                    TenantDocumentSetting::query()
                        ->where(
                            'tenant_id',
                            $tenantId
                        )
                        ->where(
                            'business_id',
                            $this->businessContext
                                ->businessId()
                        )
                        ->lockForUpdate()
                        ->first();

                if ($settings === null) {
                    return;
                }

                if (
                    $settings
                        ->signature_image_file_id
                ) {
                    $oldFile =
                        FileAsset::query()
                            ->where(
                                'tenant_id',
                                $tenantId
                            )
                            ->where(
                                'id',
                                $settings
                                    ->signature_image_file_id
                            )
                            ->first();
                }

                $settings
                    ->signature_image_file_id =
                    null;

                $settings->save();
            }
        );

        if ($oldFile !== null) {
            $this->fileService->deleteObject(
                $oldFile
            );

            $oldFile->delete();
        }

        return $this->get()->load(
            'signatureImage'
        );
    }

    private function defaultAttributes(): array
    {
        return [
            'quotation_opening_text' =>
                TenantDocumentSetting::
                    DEFAULT_QUOTATION_OPENING_TEXT,

            'quotation_closing_text' =>
                TenantDocumentSetting::
                    DEFAULT_QUOTATION_CLOSING_TEXT,

            'invoice_footnote' =>
                TenantDocumentSetting::
                    DEFAULT_INVOICE_FOOTNOTE,

            'quotation_default_terms' =>
                TenantDocumentSetting::
                    DEFAULT_QUOTATION_TERMS,

            'quotation_default_validity_days' =>
                TenantDocumentSetting::
                    DEFAULT_QUOTATION_VALIDITY_DAYS,

            'quotation_number_prefix' =>
                'PNW',

            'invoice_number_prefix' =>
                'INV',
        ];
    }

    public function signatureFile(): ?FileAsset
    {
        $settings =
            $this->get();

        if (
            $settings
                ->signature_image_file_id === null
        ) {
            return null;
        }

        return FileAsset::query()
            ->where(
                'tenant_id',
                $this->tenantContext
                    ->tenantId()
            )
            ->where(
                'id',
                $settings
                    ->signature_image_file_id
            )
            ->where(
                'purpose',
                'DOCUMENT_SIGNATURE'
            )
            ->first();
    }
}
