<?php

namespace App\Services\Payment;

use App\Models\CashAccount;
use App\Models\FileAsset;
use App\Models\Invoice;
use App\Models\InvoicePublicLink;
use App\Models\Payment;
use App\Models\TenantPaymentSetting;
use App\Services\File\FileService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class PublicInvoicePaymentService
{
    public function __construct(
        private readonly FileService $fileService,
        private readonly PublicPaymentAccountTokenService $accountTokenService
    ) {
    }

    public function submit(
        InvoicePublicLink $resolvedLink,
        array $data,
        UploadedFile $evidence
    ): Payment {
        $storedFile = null;

        try {
            return DB::transaction(
                function () use (
                    $resolvedLink,
                    $data,
                    $evidence,
                    &$storedFile
                ): Payment {
                    /*
                     * Lock public credential lebih dulu,
                     * lalu invoice. Ini juga mencegah dua
                     * submission bersamaan untuk invoice
                     * yang sama.
                     */
                    $link =
                        InvoicePublicLink::query()
                            ->where(
                                'id',
                                $resolvedLink->id
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    if (
                        $link->revoked_at !== null
                        || (
                            $link->expires_at !== null
                            && now()->greaterThanOrEqualTo(
                                $link->expires_at
                            )
                        )
                    ) {
                        throw (
                            new ModelNotFoundException()
                        )->setModel(
                            InvoicePublicLink::class,
                            [$link->id]
                        );
                    }

                    $invoice =
                        Invoice::query()
                            ->where(
                                'tenant_id',
                                $link->tenant_id
                            )
                            ->where(
                                'business_id',
                                $link->business_id
                            )
                            ->where(
                                'id',
                                $link->invoice_id
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    $this->assertInvoiceCanReceivePayment(
                        $invoice
                    );

                    $settings =
                        TenantPaymentSetting::query()
                            ->where(
                                'tenant_id',
                                $invoice->tenant_id
                            )
                            ->where(
                                'business_id',
                                $invoice->business_id
                            )
                            ->first();

                    if (
                        ! (
                            $settings
                                ?->bank_transfer_enabled
                            ?? false
                        )
                    ) {
                        throw ValidationException::withMessages([
                            'payment_account_token' => [
                                'Transfer bank tidak tersedia untuk tagihan ini.',
                            ],
                        ]);
                    }

                    $tokenData =
                        $this->accountTokenService
                            ->decode(
                                $data[
                                    'payment_account_token'
                                ]
                            );

                    if (
                        $tokenData['tenant_id']
                            !== $invoice->tenant_id
                        || $tokenData['business_id']
                            !== $invoice->business_id
                    ) {
                        throw ValidationException::withMessages([
                            'payment_account_token' => [
                                'Rekening tujuan pembayaran tidak valid.',
                            ],
                        ]);
                    }

                    $account =
                        CashAccount::query()
                            ->where(
                                'tenant_id',
                                $invoice->tenant_id
                            )
                            ->where(
                                'business_id',
                                $invoice->business_id
                            )
                            ->where(
                                'id',
                                $tokenData[
                                    'cash_account_id'
                                ]
                            )
                            ->where(
                                'type',
                                'BANK'
                            )
                            ->where(
                                'status',
                                'ACTIVE'
                            )
                            ->where(
                                'accepts_payments',
                                true
                            )
                            ->lockForUpdate()
                            ->first();

                    if ($account === null) {
                        throw ValidationException::withMessages([
                            'payment_account_token' => [
                                'Rekening tujuan pembayaran tidak tersedia.',
                            ],
                        ]);
                    }

                    if (
                        $account->currency
                        !== $invoice->currency
                    ) {
                        throw ValidationException::withMessages([
                            'payment_account_token' => [
                                'Mata uang rekening tujuan tidak sesuai dengan tagihan.',
                            ],
                        ]);
                    }

                    $amountMinor =
                        $this->minorUnits(
                            $data['amount']
                        );

                    $outstandingMinor =
                        $this->minorUnits(
                            $invoice
                                ->outstanding_amount
                            ?? '0.00'
                        );

                    if (
                        $amountMinor <= 0
                        || $amountMinor
                            > $outstandingMinor
                    ) {
                        throw ValidationException::withMessages([
                            'amount' => [
                                'Nominal pembayaran melebihi sisa tagihan atau tidak valid.',
                            ],
                        ]);
                    }

                    $partialPaymentEnabled =
                        (bool) (
                            $settings
                                ?->partial_payment_enabled
                            ?? true
                        );

                    if (
                        ! $partialPaymentEnabled
                        && $amountMinor
                            !== $outstandingMinor
                    ) {
                        throw ValidationException::withMessages([
                            'amount' => [
                                'Pembayaran sebagian tidak diizinkan untuk tagihan ini.',
                            ],
                        ]);
                    }

                    /*
                     * Invoice lock membuat pengecekan ini
                     * serial untuk satu invoice.
                     */
                    $pendingExists =
                        Payment::query()
                            ->where(
                                'tenant_id',
                                $invoice->tenant_id
                            )
                            ->where(
                                'business_id',
                                $invoice->business_id
                            )
                            ->where(
                                'intended_invoice_id',
                                $invoice->id
                            )
                            ->where(
                                'status',
                                'PENDING'
                            )
                            ->exists();

                    if ($pendingExists) {
                        throw ValidationException::withMessages([
                            'amount' => [
                                'Konfirmasi pembayaran sebelumnya masih menunggu verifikasi.',
                            ],
                        ]);
                    }

                    /*
                     * Public uploader tidak memiliki User.
                     * File tetap PRIVATE dan tenant berasal
                     * dari invoice hasil resolve token.
                     */
                    $storedFile =
                        $this->fileService
                            ->storePaymentProofForTenant(
                                $invoice->tenant_id,
                                $evidence,
                                null
                            );

                    $reference =
                        isset($data['reference'])
                            ? trim(
                                (string)
                                $data['reference']
                            )
                            : null;

                    if ($reference === '') {
                        $reference = null;
                    }

                    return Payment::query()->create([
                        'id' =>
                            (string) Str::ulid(),

                        'tenant_id' =>
                            $invoice->tenant_id,

                        'business_id' =>
                            $invoice->business_id,

                        'customer_id' =>
                            $invoice->customer_id,

                        'intended_invoice_id' =>
                            $invoice->id,

                        'cash_account_id' =>
                            $account->id,

                        'amount' =>
                            $this->minorToDecimal(
                                $amountMinor
                            ),

                        'currency' =>
                            $invoice->currency,

                        'paid_at' =>
                            $data['paid_at'],

                        'method' =>
                            'BANK_TRANSFER',

                        'status' =>
                            'PENDING',

                        'reference' =>
                            $reference,

                        'evidence_file_id' =>
                            $storedFile->id,

                        'created_by_user_id' =>
                            null,
                    ]);
                }
            );
        } catch (Throwable $exception) {
            /*
             * DB rollback akan menghapus row FileAsset,
             * tetapi object storage bukan bagian transaksi.
             */
            if ($storedFile instanceof FileAsset) {
                try {
                    $this->fileService
                        ->deleteObject(
                            $storedFile
                        );
                } catch (Throwable) {
                    // Jangan menutupi exception utama.
                }
            }

            throw $exception;
        }
    }

    private function assertInvoiceCanReceivePayment(
        Invoice $invoice
    ): void {
        if (
            ! in_array(
                $invoice->status,
                [
                    'ISSUED',
                    'PARTIALLY_PAID',
                    'OVERDUE',
                ],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'amount' => [
                    'Tagihan ini tidak dapat menerima pembayaran.',
                ],
            ]);
        }

        if (
            $this->minorUnits(
                $invoice
                    ->outstanding_amount
                ?? '0.00'
            ) <= 0
        ) {
            throw ValidationException::withMessages([
                'amount' => [
                    'Tagihan ini sudah tidak memiliki sisa pembayaran.',
                ],
            ]);
        }
    }

    private function minorUnits(
        mixed $value
    ): int {
        if (is_int($value)) {
            return $value * 100;
        }

        if (is_float($value)) {
            $value = number_format(
                $value,
                2,
                '.',
                ''
            );
        }

        $value =
            trim(
                (string) $value
            );

        if (
            ! preg_match(
                '/^\d+(?:\.\d{1,2})?$/',
                $value
            )
        ) {
            throw ValidationException::withMessages([
                'amount' => [
                    'Nominal pembayaran tidak valid.',
                ],
            ]);
        }

        [$whole, $fraction] =
            array_pad(
                explode(
                    '.',
                    $value,
                    2
                ),
                2,
                '0'
            );

        $fraction =
            str_pad(
                $fraction,
                2,
                '0'
            );

        return ((int) $whole * 100)
            + (int) substr(
                $fraction,
                0,
                2
            );
    }

    private function minorToDecimal(
        int $minor
    ): string {
        return sprintf(
            '%d.%02d',
            intdiv(
                $minor,
                100
            ),
            $minor % 100
        );
    }
}
