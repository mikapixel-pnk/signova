<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\ListPaymentsRequest;
use App\Http\Requests\Payment\RejectPaymentRequest;
use App\Http\Requests\Payment\ReversePaymentRequest;
use App\Http\Requests\Payment\StorePaymentAllocationRequest;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Requests\Payment\UploadPaymentEvidenceRequest;
use App\Http\Resources\Invoice\InvoiceResource;
use App\Http\Resources\Payment\PaymentResource;
use App\Services\File\FileService;
use App\Services\Payment\PaymentService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function index(
        ListPaymentsRequest $request,
        PaymentService $service
    ): JsonResponse {
        $data = $request->validated();

        $paginator =
            $service->paginate(
                $data['search'] ?? null,
                $data['status'] ?? null,
                $data['method'] ?? null,
                $data['customer_id'] ?? null,
                $data['per_page'] ?? 20
            );

        $items = collect(
            $paginator->items()
        )
            ->map(
                fn ($payment) =>
                    (new PaymentResource(
                        $payment
                    ))->resolve($request)
            )
            ->values()
            ->all();

        return ApiResponse::success(
            $request,
            $items,
            200,
            null,
            [
                'current_page' =>
                    $paginator->currentPage(),

                'per_page' =>
                    $paginator->perPage(),

                'total' =>
                    $paginator->total(),

                'last_page' =>
                    $paginator->lastPage(),
            ]
        );
    }

    public function store(
        StorePaymentRequest $request,
        PaymentService $service
    ): JsonResponse {
        $payment =
            $service->create(
                $request->validated()
            );

        return ApiResponse::success(
            $request,
            (new PaymentResource(
                $payment
            ))->resolve($request),
            201,
            'Pembayaran berhasil dicatat.'
        );
    }

    public function show(
        Request $request,
        string $paymentId,
        PaymentService $service
    ): JsonResponse {
        $payment =
            $service->findOrFail(
                $paymentId
            );

        return ApiResponse::success(
            $request,
            (new PaymentResource(
                $payment
            ))->resolve($request)
        );
    }

    public function verify(
        Request $request,
        string $paymentId,
        PaymentService $service
    ): JsonResponse {
        $payment =
            $service->verify(
                $paymentId
            );

        return ApiResponse::success(
            $request,
            (new PaymentResource(
                $payment
            ))->resolve($request),
            200,
            'Pembayaran berhasil diverifikasi.'
        );
    }

    public function reject(
        RejectPaymentRequest $request,
        string $paymentId,
        PaymentService $service
    ): JsonResponse {
        $payment =
            $service->reject(
                $paymentId,
                $request->validated(
                    'reason'
                )
            );

        return ApiResponse::success(
            $request,
            (new PaymentResource(
                $payment
            ))->resolve($request),
            200,
            'Pembayaran berhasil ditolak.'
        );
    }

    public function reverse(
        ReversePaymentRequest $request,
        string $paymentId,
        PaymentService $service
    ): JsonResponse {
        $result =
            $service->reverse(
                $paymentId,
                $request->validated(
                    'reason'
                )
            );

        $invoices =
            collect(
                $result['invoices']
            )
                ->map(
                    fn ($invoice) =>
                        (new InvoiceResource(
                            $invoice
                        ))->resolve($request)
                )
                ->values()
                ->all();

        return ApiResponse::success(
            $request,
            [
                'reversal' => [
                    'id' =>
                        $result['reversal']->id,

                    'payment_id' =>
                        $result['reversal']
                            ->payment_id,

                    'amount' =>
                        $result['reversal']
                            ->amount,

                    'reason' =>
                        $result['reversal']
                            ->reason,

                    'reversed_at' =>
                        $result['reversal']
                            ->reversed_at
                            ?->toISOString(),
                ],

                'payment' =>
                    (new PaymentResource(
                        $result['payment']
                    ))->resolve($request),

                'invoices' =>
                    $invoices,
            ],
            200,
            'Pembayaran berhasil dibalik.'
        );
    }

    public function allocate(
        StorePaymentAllocationRequest $request,
        string $paymentId,
        PaymentService $service
    ): JsonResponse {
        $result =
            $service->allocate(
                $paymentId,
                $request->validated()
            );

        return ApiResponse::success(
            $request,
            [
                'allocation' => [
                    'id' =>
                        $result['allocation']->id,

                    'payment_id' =>
                        $result['allocation']
                            ->payment_id,

                    'invoice_id' =>
                        $result['allocation']
                            ->invoice_id,

                    'allocated_amount' =>
                        $result['allocation']
                            ->allocated_amount,
                ],

                'payment' =>
                    (new PaymentResource(
                        $result['payment']
                    ))->resolve($request),

                'payment_allocated_amount' =>
                    $result[
                        'payment_allocated_amount'
                    ],

                'payment_unallocated_amount' =>
                    $result[
                        'payment_unallocated_amount'
                    ],

                'invoice' =>
                    (new InvoiceResource(
                        $result['invoice']
                    ))->resolve($request),
            ],
            201,
            'Pembayaran berhasil dialokasikan.'
        );
    }

    public function uploadEvidence(
        UploadPaymentEvidenceRequest $request,
        string $paymentId,
        PaymentService $service
    ): JsonResponse {
        $payment =
            $service->replaceEvidence(
                $paymentId,
                $request->file(
                    'evidence'
                )
            );

        return ApiResponse::success(
            $request,
            (new PaymentResource(
                $payment
            ))->resolve($request),
            200,
            'Bukti pembayaran berhasil disimpan.'
        );
    }

    public function evidence(
        string $paymentId,
        PaymentService $service,
        FileService $fileService
    ): StreamedResponse {
        $file =
            $service->evidenceFile(
                $paymentId
            );

        abort_if(
            $file === null,
            404
        );

        $stream =
            $fileService->readStream(
                $file
            );

        return response()->stream(
            function () use ($stream): void {
                fpassthru($stream);

                if (is_resource($stream)) {
                    fclose($stream);
                }
            },
            200,
            [
                'Content-Type' =>
                    $file->mime_type,

                'Content-Length' =>
                    (string) $file->size_bytes,

                'Content-Disposition' =>
                    'inline',

                'X-Content-Type-Options' =>
                    'nosniff',

                'Cache-Control' =>
                    'private, no-store',
            ]
        );
    }

    public function destroyEvidence(
        Request $request,
        string $paymentId,
        PaymentService $service
    ): JsonResponse {
        $payment =
            $service->removeEvidence(
                $paymentId
            );

        return ApiResponse::success(
            $request,
            (new PaymentResource(
                $payment
            ))->resolve($request),
            200,
            'Bukti pembayaran berhasil dihapus.'
        );
    }
}
