<?php

namespace App\Http\Resources\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentSettingResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        $qr =
            $this->resource
                ->relationLoaded('staticQrFile')
                ? $this->resource->staticQrFile
                : null;

        return [
            'bank_transfer_enabled' =>
                (bool) $this->bank_transfer_enabled,

            'bank_name' =>
                $this->bank_name,

            'bank_account_number' =>
                $this->bank_account_number,

            'bank_account_name' =>
                $this->bank_account_name,

            'static_qr_enabled' =>
                (bool) $this->static_qr_enabled,

            'has_static_qr' =>
                $this->static_qr_file_id !== null,

            'static_qr' =>
                $qr === null
                    ? null
                    : [
                        'id' => $qr->id,
                        'mime_type' =>
                            $qr->mime_type,
                        'size_bytes' =>
                            $qr->size_bytes,
                        'original_name' =>
                            $qr->original_name,
                    ],

            'midtrans_enabled' =>
                (bool) $this->midtrans_enabled,

            'partial_payment_enabled' =>
                (bool) $this->partial_payment_enabled,
        ];
    }
}
