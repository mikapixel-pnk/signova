<?php

namespace App\Services\Invoice\Document;

class InvoicePreviewViewModelFactory
{
    public function make(
        array $resolvedTemplate
    ): array {
        return [
            'document' => [
                'title' =>
                    'TAGIHAN',

                'number' =>
                    'INV-202609-0001',

                'status' =>
                    'ISSUED',

                'status_label' =>
                    'Terbit',

                'issued_at' =>
                    '14-09-2026',

                'due_at' =>
                    '21-09-2026',

                'currency' =>
                    'IDR',

                'notes' =>
                    'Terima kasih atas kepercayaan Anda.',
            ],

            'customer' => [
                'name' =>
                    'PT Contoh Pelanggan',
            ],

            'items' => [
                [
                    'number' =>
                        1,

                    'name' =>
                        'Pembuatan Neon Box',

                    'description' =>
                        'Ukuran 200 × 100 cm',

                    'quantity' =>
                        '1',

                    'unit' =>
                        'Unit',

                    'unit_price' =>
                        '2.500.000,00',

                    'amount' =>
                        '2.500.000,00',
                ],

                [
                    'number' =>
                        2,

                    'name' =>
                        'Jasa Instalasi',

                    'description' =>
                        'Instalasi area kota',

                    'quantity' =>
                        '1',

                    'unit' =>
                        'Paket',

                    'unit_price' =>
                        '500.000,00',

                    'amount' =>
                        '500.000,00',
                ],
            ],

            'summary' => [
                'subtotal' =>
                    '3.000.000,00',

                'discount_total' =>
                    '0,00',

                'tax_total' =>
                    '330.000,00',

                'total' =>
                    '3.330.000,00',
            ],

            'branding' => [
                'business_name' =>
                    'PT Contoh Reklame',

                'address' =>
                    'Jl. Contoh Bisnis No. 10',

                'phone' =>
                    '0812-3456-7890',

                'email' =>
                    'halo@contoh.test',

                'tax_id' =>
                    '01.234.567.8-999.000',

                'invoice_footnote' =>
                    'Pembayaran dapat dilakukan sesuai informasi yang telah disepakati.',

                'signature_name' =>
                    'Budi Santoso',

                'signature_title' =>
                    'Finance Manager',

                'signature_image_data_uri' =>
                    null,
            ],

            'template' =>
                $resolvedTemplate[
                    'template'
                ],

            'theme' =>
                $resolvedTemplate[
                    'palette'
                ]['tokens'],
        ];
    }
}
