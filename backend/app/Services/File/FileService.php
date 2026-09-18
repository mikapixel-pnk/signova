<?php

namespace App\Services\File;

use App\Models\FileAsset;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class FileService
{
    private const SIGNATURE_MIME_MAP = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    private const PAYMENT_PROOF_MIME_MAP = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    public function __construct(
        private readonly TenantContext $tenantContext
    ) {
    }

    public function storePaymentProof(
        UploadedFile $uploadedFile
    ): FileAsset {
        return $this->storePaymentProofForTenant(
            $this->tenantContext->tenantId(),
            $uploadedFile,
            $this->tenantContext->userId()
        );
    }

    public function storePaymentProofForTenant(
        string $tenantId,
        UploadedFile $uploadedFile,
        ?string $uploadedByUserId = null
    ): FileAsset {
        return $this->storePrivateFileForTenant(
            $tenantId,
            $uploadedByUserId,
            $uploadedFile,
            'PAYMENT_PROOF',
            'payment-proofs',
            self::PAYMENT_PROOF_MIME_MAP
        );
    }

    public function storePaymentQr(
        UploadedFile $uploadedFile
    ): FileAsset {
        return $this->storePrivateFile(
            $uploadedFile,
            'PAYMENT_QR',
            'payment-qr',
            self::SIGNATURE_MIME_MAP
        );
    }

    public function storeSignature(
        UploadedFile $uploadedFile
    ): FileAsset {
        return $this->storePrivateFile(
            $uploadedFile,
            'DOCUMENT_SIGNATURE',
            'document-signatures',
            self::SIGNATURE_MIME_MAP
        );
    }

    public function storeBusinessLogo(
        UploadedFile $uploadedFile
    ): FileAsset {
        return $this->storePrivateFile(
            $uploadedFile,
            'BUSINESS_LOGO',
            'business-logos',
            self::SIGNATURE_MIME_MAP
        );
    }

    private function storePrivateFile(
        UploadedFile $uploadedFile,
        string $purpose,
        string $folder,
        array $mimeMap
    ): FileAsset {
        return $this->storePrivateFileForTenant(
            $this->tenantContext->tenantId(),
            $this->tenantContext->userId(),
            $uploadedFile,
            $purpose,
            $folder,
            $mimeMap
        );
    }

    private function storePrivateFileForTenant(
        string $tenantId,
        ?string $userId,
        UploadedFile $uploadedFile,
        string $purpose,
        string $folder,
        array $mimeMap
    ): FileAsset {
        $mimeType =
            $uploadedFile->getMimeType();

        if (
            ! is_string($mimeType)
            || ! array_key_exists(
                $mimeType,
                $mimeMap
            )
        ) {
            throw new RuntimeException(
                'Unsupported image file type.'
            );
        }

        $extension =
            $mimeMap[
                $mimeType
            ];

        $fileId =
            (string) Str::ulid();

        $disk =
            (string) config(
                'filesystems.private_disk',
                'local'
            );

        if ($disk === 'public') {
            throw new RuntimeException(
                'Public disk cannot be used for private files.'
            );
        }

        $objectKey =
            'tenants/'
            . $tenantId
            . '/'
            . $folder
            . '/'
            . $fileId
            . '.'
            . $extension;

        $contents =
            file_get_contents(
                $uploadedFile->getRealPath()
            );

        if ($contents === false) {
            throw new RuntimeException(
                'Unable to read uploaded file.'
            );
        }

        $stored =
            Storage::disk($disk)->put(
                $objectKey,
                $contents,
                [
                    'visibility' => 'private',
                ]
            );

        if (! $stored) {
            throw new RuntimeException(
                'Unable to store uploaded file.'
            );
        }

        try {
            return FileAsset::query()->create([
                'id' => $fileId,
                'tenant_id' => $tenantId,
                'purpose' => $purpose,
                'storage_disk' => $disk,
                'object_key' => $objectKey,
                'original_name' =>
                    mb_substr(
                        $uploadedFile
                            ->getClientOriginalName(),
                        0,
                        255
                    ),
                'mime_type' => $mimeType,
                'size_bytes' =>
                    (int) $uploadedFile->getSize(),
                'checksum_sha256' =>
                    hash(
                        'sha256',
                        $contents
                    ),
                'visibility' => 'PRIVATE',
                'uploaded_by_user_id' => $userId,
            ]);
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete(
                $objectKey
            );

            throw $exception;
        }
    }

    public function copyPrivateImage(
        FileAsset $source,
        string $purpose,
        string $folder
    ): FileAsset {
        $tenantId =
            $this->tenantContext->tenantId();

        $userId =
            $this->tenantContext->userId();

        if ($source->tenant_id !== $tenantId) {
            throw new RuntimeException(
                'Source file does not belong to active tenant.'
            );
        }

        if (! array_key_exists(
            $source->mime_type,
            self::SIGNATURE_MIME_MAP
        )) {
            throw new RuntimeException(
                'Unsupported image file type.'
            );
        }

        $sourceDisk =
            Storage::disk(
                $source->storage_disk
            );

        if (! $sourceDisk->exists(
            $source->object_key
        )) {
            throw new RuntimeException(
                'Source private file does not exist.'
            );
        }

        $contents =
            $sourceDisk->get(
                $source->object_key
            );

        if ($contents === '') {
            throw new RuntimeException(
                'Source private file is empty.'
            );
        }

        $fileId =
            (string) Str::ulid();

        $extension =
            self::SIGNATURE_MIME_MAP[
                $source->mime_type
            ];

        $disk =
            (string) config(
                'filesystems.private_disk',
                'local'
            );

        if ($disk === 'public') {
            throw new RuntimeException(
                'Public disk cannot be used for private files.'
            );
        }

        $objectKey =
            'tenants/'
            . $tenantId
            . '/'
            . $folder
            . '/'
            . $fileId
            . '.'
            . $extension;

        $stored =
            Storage::disk($disk)->put(
                $objectKey,
                $contents,
                [
                    'visibility' => 'private',
                ]
            );

        if (! $stored) {
            throw new RuntimeException(
                'Unable to copy private file.'
            );
        }

        try {
            return FileAsset::query()->create([
                'id' =>
                    $fileId,

                'tenant_id' =>
                    $tenantId,

                'purpose' =>
                    $purpose,

                'storage_disk' =>
                    $disk,

                'object_key' =>
                    $objectKey,

                'original_name' =>
                    $source->original_name,

                'mime_type' =>
                    $source->mime_type,

                'size_bytes' =>
                    strlen($contents),

                'checksum_sha256' =>
                    hash(
                        'sha256',
                        $contents
                    ),

                'visibility' =>
                    'PRIVATE',

                'uploaded_by_user_id' =>
                    $userId,
            ]);
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete(
                $objectKey
            );

            throw $exception;
        }
    }

    public function deleteObject(
        FileAsset $file
    ): void {
        Storage::disk(
            $file->storage_disk
        )->delete(
            $file->object_key
        );
    }

    public function readStream(
        FileAsset $file
    ) {
        $stream = Storage::disk(
            $file->storage_disk
        )->readStream(
            $file->object_key
        );

        if ($stream === false) {
            throw new RuntimeException(
                'Unable to read stored file.'
            );
        }

        return $stream;
    }
}
