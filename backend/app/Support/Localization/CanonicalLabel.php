<?php

namespace App\Support\Localization;

final class CanonicalLabel
{
    public static function status(?string $code): ?string
    {
        return match ($code) {
            'ACTIVE' => 'Aktif',
            'INACTIVE' => 'Nonaktif',
            'DRAFT' => 'Draf',
            'ISSUED' => 'Diterbitkan',
            'PARTIALLY_PAID' => 'Dibayar Sebagian',
            'PAID' => 'Lunas',
            'OVERDUE' => 'Jatuh Tempo',
            'VOID' => 'Dibatalkan',
            null => null,
            default => $code,
        };
    }

    public static function quotationStatus(
        ?string $code
    ): ?string {
        return match ($code) {
            'DRAFT' => 'Draf',
            'SENT' => 'Terkirim',
            'VIEWED' => 'Sudah Dilihat',
            'APPROVED' => 'Disetujui',
            'REJECTED' => 'Ditolak',
            'EXPIRED' => 'Kedaluwarsa',
            'CANCELLED' => 'Dibatalkan',
            null => null,
            default => $code,
        };
    }

    public static function catalogType(?string $code): ?string
    {
        return match ($code) {
            'PRODUCT' => 'Barang',
            'SERVICE' => 'Jasa',
            null => null,
            default => $code,
        };
    }

    public static function pricingMethod(?string $code): ?string
    {
        return match ($code) {
            'STANDARD' => 'Harga Standar',
            'AREA' => 'Berdasarkan Luas',
            'LENGTH' => 'Berdasarkan Panjang',
            'VOLUME' => 'Berdasarkan Volume',
            'TIME' => 'Berdasarkan Waktu',
            'PACKAGE' => 'Harga Paket',
            'MANUAL' => 'Harga Manual',
            null => null,
            default => $code,
        };
    }

    public static function unitType(?string $code): ?string
    {
        return match ($code) {
            'COUNT' => 'Jumlah',
            'LENGTH' => 'Panjang',
            'AREA' => 'Luas',
            'VOLUME' => 'Volume',
            'TIME' => 'Waktu',
            'PACKAGE' => 'Paket',
            'OTHER' => 'Lainnya',
            null => null,
            default => $code,
        };
    }
}
