<?php

namespace App\Http\Resources\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentSettingResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'business_name' =>
                $this->business_name,

            'address' =>
                $this->address,

            'phone' =>
                $this->phone,

            'email' =>
                $this->email,

            'tax_id' =>
                $this->tax_id,

            'quotation_footer' =>
                $this->quotation_footer,

            'invoice_footnote' =>
                $this->invoice_footnote,

            'signature_name' =>
                $this->signature_name,

            'signature_title' =>
                $this->signature_title,

            'has_signature_image' =>
                $this->signature_image_file_id
                !== null,

            'signature_image' =>
                $this->signatureImage
                    ? [
                        'id' =>
                            $this
                                ->signatureImage
                                ->id,

                        'mime_type' =>
                            $this
                                ->signatureImage
                                ->mime_type,

                        'size_bytes' =>
                            $this
                                ->signatureImage
                                ->size_bytes,

                        'original_name' =>
                            $this
                                ->signatureImage
                                ->original_name,
                    ]
                    : null,
        ];
    }
}
