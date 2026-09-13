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

    public function __construct(
        private readonly TenantContext $tenantContext
    ) {
    }

    public function storePaymentQr(
        UploadedFile $uploadedFile
    ): FileAsset {
        return $this->storePrivateImage(
            $uploadedFile,
            'PAYMENT_QR',
            'payment-qr'
        );
    }

    public function storeSignature(
        UploadedFile $uploadedFile
    ): FileAsset {
        return $this->storePrivateImage(
            $uploadedFile,
            'DOCUMENT_SIGNATURE',
            'document-signatures'
        );
    }

    private function storePrivateImage(
        UploadedFile $uploadedFile,
        string $purpose,
        string $folder
    ): FileAsset {
        $tenantId =
            $this->tenantContext->tenantId();

        $userId =
            $this->tenantContext->userId();

        $mimeType =
            $uploadedFile->getMimeType();

        if (
            ! is_string($mimeType)
            || ! array_key_exists(
                $mimeType,
                self::SIGNATURE_MIME_MAP
            )
        ) {
            throw new RuntimeException(
                'Unsupported image file type.'
            );
        }

        $extension =
            self::SIGNATURE_MIME_MAP[
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
