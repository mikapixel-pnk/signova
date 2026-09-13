<?php

namespace App\Http\Resources\Payment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'id' =>
                $this->id,

            'customer_id' =>
                $this->customer_id,

            'customer' =>
                $this->whenLoaded(
                    'customer',
                    fn () => [
                        'id' =>
                            $this->customer->id,

                        'code' =>
                            $this->customer->code,

                        'name' =>
                            $this->customer->name,
                    ]
                ),

            'cash_account_id' =>
                $this->cash_account_id,

            'cash_account' =>
                $this->whenLoaded(
                    'cashAccount',
                    fn () =>
                        $this->cashAccount === null
                            ? null
                            : [
                                'id' =>
                                    $this->cashAccount->id,

                                'name' =>
                                    $this->cashAccount->name,

                                'type' =>
                                    $this->cashAccount->type,
                            ]
                ),

            'amount' =>
                $this->amount,

            'currency' =>
                $this->currency,

            'paid_at' =>
                $this->paid_at?->toISOString(),

            'method' =>
                $this->method,

            'status' =>
                $this->status,

            'reference' =>
                $this->reference,

            'has_evidence' =>
                $this->evidence_file_id !== null,

            'evidence' =>
                $this->whenLoaded(
                    'evidenceFile',
                    fn () =>
                        $this->evidenceFile === null
                            ? null
                            : [
                                'id' =>
                                    $this->evidenceFile->id,

                                'original_name' =>
                                    $this->evidenceFile
                                        ->original_name,

                                'mime_type' =>
                                    $this->evidenceFile
                                        ->mime_type,

                                'size_bytes' =>
                                    $this->evidenceFile
                                        ->size_bytes,
                            ]
                ),

            'provider' =>
                $this->provider,

            'provider_reference' =>
                $this->provider_reference,

            'provider_transaction_id' =>
                $this->provider_transaction_id,

            'verified_at' =>
                $this->verified_at?->toISOString(),

            'rejected_at' =>
                $this->rejected_at?->toISOString(),

            'rejection_reason' =>
                $this->rejection_reason,

            'created_at' =>
                $this->created_at?->toISOString(),

            'updated_at' =>
                $this->updated_at?->toISOString(),
        ];
    }
}
