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

    public function storeSignature(
        UploadedFile $uploadedFile
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
                'Unsupported signature file type.'
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

        $objectKey =
            'tenants/'
            . $tenantId
            . '/document-signatures/'
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

        $stored = Storage::disk(
            $disk
        )->put(
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
                'id' =>
                    $fileId,

                'tenant_id' =>
                    $tenantId,

                'purpose' =>
                    'DOCUMENT_SIGNATURE',

                'storage_disk' =>
                    $disk,

                'object_key' =>
                    $objectKey,

                'original_name' =>
                    $uploadedFile
                        ->getClientOriginalName(),

                'mime_type' =>
                    $mimeType,

                'size_bytes' =>
                    $uploadedFile->getSize(),

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
            Storage::disk(
                $disk
            )->delete(
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
