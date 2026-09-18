<?php

namespace App\Http\Requests\Public\Invoice;

use Illuminate\Foundation\Http\FormRequest;

class SubmitPublicInvoicePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_account_token' => [
                'required',
                'string',
                'max:4096',
            ],

            'amount' => [
                'required',
                'string',
                'regex:/^\d+(?:\.\d{1,2})?$/',
            ],

            'paid_at' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:today',
            ],

            'reference' => [
                'nullable',
                'string',
                'max:255',
            ],

            'evidence' => [
                'required',
                'file',
                'mimetypes:image/png,image/jpeg,image/webp,application/pdf',
                'max:5120',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_account_token.required' =>
                'Rekening tujuan pembayaran wajib dipilih.',

            'amount.required' =>
                'Nominal pembayaran wajib diisi.',

            'amount.regex' =>
                'Nominal pembayaran tidak valid.',

            'paid_at.required' =>
                'Tanggal pembayaran wajib diisi.',

            'paid_at.before_or_equal' =>
                'Tanggal pembayaran tidak boleh di masa depan.',

            'evidence.required' =>
                'Bukti pembayaran wajib diunggah.',

            'evidence.mimetypes' =>
                'Bukti pembayaran harus berupa PNG, JPG, WebP, atau PDF.',

            'evidence.max' =>
                'Ukuran bukti pembayaran maksimal 5 MB.',
        ];
    }
}
