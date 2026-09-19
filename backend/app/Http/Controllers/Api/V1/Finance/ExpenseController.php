<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ListExpensesRequest;
use App\Http\Requests\Finance\RecordExpenseRequest;
use App\Http\Requests\Finance\RejectExpenseRequest;
use App\Http\Requests\Finance\StoreExpenseRequest;
use App\Http\Requests\Finance\UploadExpenseEvidenceRequest;
use App\Http\Requests\Finance\UpdateExpenseRequest;
use App\Http\Requests\Finance\VoidExpenseRequest;
use App\Http\Resources\Finance\ExpenseResource;
use App\Services\File\FileService;
use App\Services\Finance\ExpenseService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller
{
    public function index(
        ListExpensesRequest $request,
        ExpenseService $service
    ): JsonResponse {
        $data = $request->validated();

        $paginator =
            $service->paginate(
                $data['search'] ?? null,
                $data['status'] ?? null,
                $data['cash_account_id'] ?? null,
                $data['from'] ?? null,
                $data['to'] ?? null,
                $data['per_page'] ?? 20
            );

        $items = collect(
            $paginator->items()
        )
            ->map(
                fn ($expense) =>
                    (new ExpenseResource(
                        $expense
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
        StoreExpenseRequest $request,
        ExpenseService $service
    ): JsonResponse {
        $expense =
            $service->create(
                $request->validated()
            );

        return ApiResponse::success(
            $request,
            (new ExpenseResource(
                $expense
            ))->resolve($request),
            201,
            'Pengeluaran berhasil dibuat.'
        );
    }

    public function record(
        RecordExpenseRequest $request,
        ExpenseService $service
    ): JsonResponse {
        $expense =
            $service->record(
                $request->validated()
            );

        return ApiResponse::success(
            $request,
            (new ExpenseResource(
                $expense
            ))->resolve($request),
            201,
            'Pengeluaran berhasil dicatat.'
        );
    }


    public function show(
        Request $request,
        string $expenseId,
        ExpenseService $service
    ): JsonResponse {
        return ApiResponse::success(
            $request,
            (new ExpenseResource(
                $service->findOrFail(
                    $expenseId
                )
            ))->resolve($request)
        );
    }

    public function update(
        UpdateExpenseRequest $request,
        string $expenseId,
        ExpenseService $service
    ): JsonResponse {
        $expense =
            $service->update(
                $expenseId,
                $request->validated()
            );

        return ApiResponse::success(
            $request,
            (new ExpenseResource(
                $expense
            ))->resolve($request),
            200,
            'Pengeluaran berhasil diperbarui.'
        );
    }

    public function destroy(
        Request $request,
        string $expenseId,
        ExpenseService $service
    ): JsonResponse {
        $service->delete(
            $expenseId
        );

        return ApiResponse::success(
            $request,
            [
                'id' =>
                    $expenseId,
            ],
            200,
            'Pengeluaran berhasil dihapus.'
        );
    }

    public function uploadEvidence(
        UploadExpenseEvidenceRequest $request,
        string $expenseId,
        ExpenseService $service
    ): JsonResponse {
        $expense =
            $service->replaceEvidence(
                $expenseId,
                $request->file(
                    'evidence'
                )
            );

        return ApiResponse::success(
            $request,
            (new ExpenseResource(
                $expense
            ))->resolve($request),
            200,
            'Bukti pengeluaran berhasil disimpan.'
        );
    }

    public function evidence(
        string $expenseId,
        ExpenseService $service,
        FileService $fileService
    ): StreamedResponse {
        $file =
            $service->evidenceFile(
                $expenseId
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
        string $expenseId,
        ExpenseService $service
    ): JsonResponse {
        $expense =
            $service->removeEvidence(
                $expenseId
            );

        return ApiResponse::success(
            $request,
            (new ExpenseResource(
                $expense
            ))->resolve($request),
            200,
            'Bukti pengeluaran berhasil dihapus.'
        );
    }

    public function submit(
        Request $request,
        string $expenseId,
        ExpenseService $service
    ): JsonResponse {
        $expense =
            $service->submit(
                $expenseId
            );

        return ApiResponse::success(
            $request,
            (new ExpenseResource(
                $expense
            ))->resolve($request),
            200,
            'Pengeluaran berhasil diajukan.'
        );
    }

    public function approve(
        Request $request,
        string $expenseId,
        ExpenseService $service
    ): JsonResponse {
        $expense =
            $service->approve(
                $expenseId
            );

        return ApiResponse::success(
            $request,
            (new ExpenseResource(
                $expense
            ))->resolve($request),
            200,
            'Pengeluaran berhasil disetujui.'
        );
    }

    public function reject(
        RejectExpenseRequest $request,
        string $expenseId,
        ExpenseService $service
    ): JsonResponse {
        $expense =
            $service->reject(
                $expenseId,
                $request->validated()['reason']
            );

        return ApiResponse::success(
            $request,
            (new ExpenseResource(
                $expense
            ))->resolve($request),
            200,
            'Pengeluaran berhasil ditolak.'
        );
    }

    public function revise(
        Request $request,
        string $expenseId,
        ExpenseService $service
    ): JsonResponse {
        $expense =
            $service->revise(
                $expenseId
            );

        return ApiResponse::success(
            $request,
            (new ExpenseResource(
                $expense
            ))->resolve($request),
            200,
            'Pengeluaran dikembalikan ke Draf.'
        );
    }

    public function post(
        Request $request,
        string $expenseId,
        ExpenseService $service
    ): JsonResponse {
        $expense =
            $service->post(
                $expenseId
            );

        return ApiResponse::success(
            $request,
            (new ExpenseResource(
                $expense
            ))->resolve($request),
            200,
            'Pengeluaran berhasil diposting.'
        );
    }

    public function void(
        VoidExpenseRequest $request,
        string $expenseId,
        ExpenseService $service
    ): JsonResponse {
        $expense =
            $service->void(
                $expenseId,
                $request->validated()['reason']
            );

        return ApiResponse::success(
            $request,
            (new ExpenseResource(
                $expense
            ))->resolve($request),
            200,
            'Pengeluaran berhasil dibatalkan.'
        );
    }
}
