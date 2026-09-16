<?php

namespace App\Services\Settings;

use App\Models\FileAsset;
use App\Models\TenantPaymentSetting;
use App\Services\File\FileService;
use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class PaymentSettingService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BusinessContext $businessContext,
        private readonly FileService $fileService
    ) {
    }

    public function get(): TenantPaymentSetting
    {
        $tenantId =
            $this->tenantContext->tenantId();

        $businessId =
            $this->businessContext->businessId();

        $settings =
            TenantPaymentSetting::query()
                ->where(
                    'tenant_id',
                    $tenantId
                )
                ->where(
                    'business_id',
                    $businessId
                )
                ->first();

        if ($settings !== null) {
            return $settings;
        }

        $settings =
            new TenantPaymentSetting();

        $settings->tenant_id =
            $tenantId;

        $settings->business_id =
            $businessId;

        $settings->bank_transfer_enabled =
            false;

        $settings->static_qr_enabled =
            false;

        $settings->midtrans_enabled =
            false;

        $settings->partial_payment_enabled =
            true;

        return $settings;
    }

    public function update(
        array $data
    ): TenantPaymentSetting {
        $settings = $this->get();

        $settings->fill($data);
        $settings->save();

        return $settings->fresh([
            'staticQrFile',
        ]);
    }

    public function replaceStaticQr(
        UploadedFile $uploadedFile
    ): TenantPaymentSetting {
        $newFile =
            $this->fileService
                ->storePaymentQr(
                    $uploadedFile
                );

        $tenantId =
            $this->tenantContext->tenantId();

        $businessId =
            $this->businessContext->businessId();

        $oldFile = null;

        try {
            DB::transaction(
                function () use (
                    $tenantId,
                    $businessId,
                    $newFile,
                    &$oldFile
                ): void {
                    $settings =
                        TenantPaymentSetting::query()
                            ->where(
                                'tenant_id',
                                $tenantId
                            )
                            ->where(
                                'business_id',
                                $businessId
                            )
                            ->lockForUpdate()
                            ->first();

                    if ($settings === null) {
                        $settings =
                            new TenantPaymentSetting();

                        $settings->tenant_id =
                            $tenantId;

                        $settings->business_id =
                            $businessId;

                        $settings
                            ->bank_transfer_enabled =
                            false;

                        $settings
                            ->static_qr_enabled =
                            false;

                        $settings
                            ->midtrans_enabled =
                            false;

                        $settings
                            ->partial_payment_enabled =
                            true;
                    }

                    if (
                        $settings
                            ->static_qr_file_id
                        !== null
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
                                        ->static_qr_file_id
                                )
                                ->where(
                                    'purpose',
                                    'PAYMENT_QR'
                                )
                                ->first();
                    }

                    $settings
                        ->static_qr_file_id =
                        $newFile->id;

                    $settings
                        ->static_qr_enabled =
                        true;

                    $settings->save();
                }
            );
        } catch (\Throwable $exception) {
            $this->fileService
                ->deleteObject(
                    $newFile
                );

            $newFile->delete();

            throw $exception;
        }

        if ($oldFile !== null) {
            $this->fileService
                ->deleteObject(
                    $oldFile
                );

            $oldFile->delete();
        }

        return $this->get()->load(
            'staticQrFile'
        );
    }

    public function removeStaticQr(): TenantPaymentSetting
    {
        $tenantId =
            $this->tenantContext->tenantId();

        $businessId =
            $this->businessContext->businessId();

        $oldFile = null;

        DB::transaction(
            function () use (
                $tenantId,
                $businessId,
                &$oldFile
            ): void {
                $settings =
                    TenantPaymentSetting::query()
                        ->where(
                            'tenant_id',
                            $tenantId
                        )
                        ->where(
                            'business_id',
                            $businessId
                        )
                        ->lockForUpdate()
                        ->first();

                if ($settings === null) {
                    return;
                }

                if (
                    $settings
                        ->static_qr_file_id
                    !== null
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
                                    ->static_qr_file_id
                            )
                            ->where(
                                'purpose',
                                'PAYMENT_QR'
                            )
                            ->first();
                }

                $settings
                    ->static_qr_file_id =
                    null;

                $settings
                    ->static_qr_enabled =
                    false;

                $settings->save();
            }
        );

        if ($oldFile !== null) {
            $this->fileService
                ->deleteObject(
                    $oldFile
                );

            $oldFile->delete();
        }

        return $this->get()->load(
            'staticQrFile'
        );
    }

    public function staticQrFile(): ?FileAsset
    {
        $settings = $this->get();

        if (
            $settings->static_qr_file_id
            === null
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
                    ->static_qr_file_id
            )
            ->where(
                'purpose',
                'PAYMENT_QR'
            )
            ->first();
    }
}
