<?php

namespace App\Http\Requests\Invoice;

use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $data = [];

        if ($this->exists('notes')) {
            $data['notes'] =
                is_string($this->notes)
                    ? trim($this->notes)
                    : $this->notes;
        }

        $this->merge($data);
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                if (
                    ! $this->exists('customer_id')
                    && ! $this->exists('due_at')
                    && ! $this->exists('notes')
                    && ! $this->exists('items')
                    && ! $this->exists('global_discount_type')
                    && ! $this->exists('global_discount_value')
                    && ! $this->exists('tax_enabled')
                    && ! $this->exists('tax_rate')
                ) {
                    $validator->errors()->add(
                        'invoice',
                        'Minimal satu data tagihan harus diubah.'
                    );
                }

                $adjustmentChanged =
                    $this->exists(
                        'global_discount_type'
                    )
                    || $this->exists(
                        'global_discount_value'
                    )
                    || $this->exists(
                        'tax_enabled'
                    )
                    || $this->exists(
                        'tax_rate'
                    );

                if (
                    $adjustmentChanged
                    && ! $this->exists(
                        'items'
                    )
                ) {
                    $validator->errors()->add(
                        'items',
                        'Item tagihan harus dikirim ulang saat diskon global atau pajak diubah.'
                    );
                }

                if (
                    $this->exists(
                        'global_discount_value'
                    )
                    && $this->input(
                        'global_discount_value'
                    ) !== null
                    && $this->input(
                        'global_discount_value'
                    ) !== ''
                    && ! $this->filled(
                        'global_discount_type'
                    )
                ) {
                    $validator->errors()->add(
                        'global_discount_type',
                        'Pilih jenis diskon global.'
                    );
                }
            },
        ];
    }

    public function rules(): array
    {
        $tenantId =
            app(TenantContext::class)
                ->tenantId();

        $businessId =
            app(BusinessContext::class)
                ->businessId();

        $pricingMethods =
            'STANDARD,AREA,LENGTH,VOLUME,TIME,PACKAGE,MANUAL';

        return [
            'customer_id' => [
                'sometimes',
                'string',
                Rule::exists(
                    'customers',
                    'id'
                )->where(
                    fn ($query) =>
                        $query
                            ->where(
                                'tenant_id',
                                $tenantId
                            )
                            ->where(
                                'business_id',
                                $businessId
                            )
                ),
            ],

            'due_at' => [
                'sometimes',
                'nullable',
                'date',
            ],

            'notes' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'global_discount_type' => [
                'sometimes',
                'nullable',
                'in:PERCENT,NOMINAL',
            ],

            'global_discount_value' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                Rule::requiredIf(
                    fn () =>
                        $this->filled(
                            'global_discount_type'
                        )
                ),
            ],

            'tax_enabled' => [
                'sometimes',
                'boolean',
            ],

            'tax_rate' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                'max:100',
                Rule::requiredIf(
                    fn () =>
                        $this->boolean(
                            'tax_enabled'
                        )
                ),
            ],

            'items' => [
                'sometimes',
                'array',
                'min:1',
            ],

            'items.*.catalog_item_id' => [
                'sometimes',
                'nullable',
                'string',
                Rule::exists(
                    'catalog_items',
                    'id'
                )->where(
                    fn ($query) =>
                        $query
                            ->where(
                                'tenant_id',
                                $tenantId
                            )
                            ->where(
                                'business_id',
                                $businessId
                            )
                ),
            ],

            'items.*.unit_id' => [
                'sometimes',
                'nullable',
                'string',
                Rule::exists(
                    'units',
                    'id'
                )->where(
                    fn ($query) =>
                        $query
                            ->where(
                                'tenant_id',
                                $tenantId
                            )
                            ->where(
                                'business_id',
                                $businessId
                            )
                ),
            ],

            'items.*.item_type' => [
                'sometimes',
                'nullable',
                'in:PRODUCT,SERVICE',
            ],

            'items.*.code' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'items.*.name' => [
                'required_without:items.*.catalog_item_id',
                'nullable',
                'string',
                'max:190',
            ],

            'items.*.description' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'items.*.quantity' => [
                'sometimes',
                'numeric',
                'gt:0',
            ],

            'items.*.pricing_method' => [
                'required_without:items.*.catalog_item_id',
                'nullable',
                'in:' . $pricingMethods,
            ],

            'items.*.pricing_config' => [
                'sometimes',
                'nullable',
                'array',
            ],

            'items.*.pricing_config.width' => [
                'sometimes',
                'numeric',
                'gt:0',
            ],

            'items.*.pricing_config.height' => [
                'sometimes',
                'numeric',
                'gt:0',
            ],

            'items.*.pricing_config.depth' => [
                'sometimes',
                'numeric',
                'gt:0',
            ],

            'items.*.pricing_config.length' => [
                'sometimes',
                'numeric',
                'gt:0',
            ],

            'items.*.pricing_config.duration' => [
                'sometimes',
                'numeric',
                'gt:0',
            ],

            'items.*.unit_price' => [
                'required_without:items.*.catalog_item_id',
                'nullable',
                'numeric',
                'min:0',
            ],

            'items.*.discount_amount' => [
                'sometimes',
                'numeric',
                'min:0',
            ],

            'items.*.tax_rate' => [
                'sometimes',
                'numeric',
                'min:0',
                'max:100',
            ],

            'items.*.sort_order' => [
                'sometimes',
                'integer',
                'min:0',
            ],

            'tenant_id' => ['prohibited'],
            'business_id' => ['prohibited'],
            'invoice_number' => ['prohibited'],
            'project_id' => ['prohibited'],
            'source_quotation_id' => ['prohibited'],
            'source_quotation_version_id' => ['prohibited'],
            'status' => ['prohibited'],
            'issued_at' => ['prohibited'],
            'currency' => ['prohibited'],
            'subtotal' => ['prohibited'],
            'discount_total' => ['prohibited'],
            'tax_total' => ['prohibited'],
            'total' => ['prohibited'],
            'paid_amount' => ['prohibited'],
            'outstanding_amount' => ['prohibited'],
            'invoice_template_key' => ['prohibited'],
            'invoice_palette_key' => ['prohibited'],
            'invoice_template_version' => ['prohibited'],
            'branding_snapshot' => ['prohibited'],
            'branding_logo_file_id' => ['prohibited'],
            'branding_signature_file_id' => ['prohibited'],

            'items.*.tax_amount' => [
                'prohibited',
            ],

            'items.*.amount' => [
                'prohibited',
            ],
        ];
    }
}
