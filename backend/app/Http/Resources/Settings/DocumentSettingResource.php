<?php

namespace App\Http\Resources\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentSettingResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        $business =
            $this->business;

        return [
            'business_name' =>
                $business?->name,

            'legal_name' =>
                $business?->legal_name,

            'address' =>
                $business?->address,

            'city' =>
                $business?->city,

            'province' =>
                $business?->province,

            'postal_code' =>
                $business?->postal_code,

            'phone' =>
                $business?->phone,

            'whatsapp' =>
                $business?->whatsapp,

            'email' =>
                $business?->email,

            'website' =>
                $business?->website,

            'tax_id' =>
                $business?->tax_id,

            'quotation_opening_text' =>
                $this->quotation_opening_text,

            'quotation_closing_text' =>
                $this->quotation_closing_text,

            'quotation_default_terms' =>
                $this->quotation_default_terms,

            'quotation_default_validity_days' =>
                $this->quotation_default_validity_days,

            'quotation_number_prefix' =>
                $this->quotation_number_prefix,

            'invoice_number_prefix' =>
                $this->invoice_number_prefix,

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
