<?php

use App\Exceptions\Quotation\InvalidQuotationTransitionException;
use App\Exceptions\Quotation\QuotationNotEditableException;
use App\Exceptions\Quotation\QuotationPricingValidationException;
use App\Http\Middleware\ResolveTenantContext;
use App\Http\Middleware\RequireCapability;
use App\Http\Middleware\AssignRequestId;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use App\Support\Api\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(
            prepend: [
                AssignRequestId::class,
            ],
        );

        $middleware->alias([
            'tenant.context' => ResolveTenantContext::class,
            'capability' => RequireCapability::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) =>
                $request->is('api/*') ||
                $request->expectsJson(),
        );

        $exceptions->render(
            function (
                ValidationException $exception,
                Request $request
            ) {
                if (! $request->is('api/*')) {
                    return null;
                }

                return ApiResponse::error(
                    $request,
                    'VALIDATION_FAILED',
                    'Periksa kembali data yang dimasukkan.',
                    422,
                    [
                        'fields' =>
                            $exception->errors(),
                    ]
                );
            }
        );

        $exceptions->render(
            function (
                \App\Exceptions\Invoice\InvalidInvoiceTransitionException $exception,
                Request $request
            ) {
                if (! $request->is('api/*')) {
                    return null;
                }

                return ApiResponse::error(
                    $request,
                    'INVALID_TRANSITION',
                    $exception->getMessage(),
                    409
                );
            }
        );

        $exceptions->render(
            function (
                InvalidQuotationTransitionException $exception,
                Request $request
            ) {
                if (! $request->is('api/*')) {
                    return null;
                }

                return ApiResponse::error(
                    $request,
                    'INVALID_TRANSITION',
                    $exception->getMessage(),
                    409
                );
            }
        );

        $exceptions->render(
            function (
                QuotationNotEditableException $exception,
                Request $request
            ) {
                if (! $request->is('api/*')) {
                    return null;
                }

                return ApiResponse::error(
                    $request,
                    'QUOTATION_NOT_EDITABLE',
                    $exception->getMessage(),
                    409
                );
            }
        );

        $exceptions->render(
            function (
                \App\Exceptions\Invoice\QuotationToInvoiceConflictException $exception,
                Request $request
            ) {
                if (! $request->is('api/*')) {
                    return null;
                }

                return ApiResponse::error(
                    $request,
                    $exception->errorCode(),
                    $exception->getMessage(),
                    409
                );
            }
        );

        $exceptions->render(
            function (
                QuotationPricingValidationException $exception,
                Request $request
            ) {
                if (! $request->is('api/*')) {
                    return null;
                }

                return ApiResponse::error(
                    $request,
                    'VALIDATION_FAILED',
                    'Periksa kembali data yang dimasukkan.',
                    422,
                    [
                        'fields' => [
                            $exception->field() => [
                                $exception->getMessage(),
                            ],
                        ],
                    ]
                );
            }
        );

        $exceptions->render(
            function (
                AuthenticationException $exception,
                Request $request
            ) {
                if (! $request->is('api/*')) {
                    return null;
                }

                return ApiResponse::error(
                    $request,
                    'AUTH_REQUIRED',
                    'Autentikasi diperlukan.',
                    401
                );
            }
        );

        $exceptions->render(
            function (
                AuthorizationException $exception,
                Request $request
            ) {
                if (! $request->is('api/*')) {
                    return null;
                }

                return ApiResponse::error(
                    $request,
                    'FORBIDDEN',
                    'Anda tidak memiliki hak akses untuk tindakan ini.',
                    403
                );
            }
        );

        $exceptions->render(
            function (
                ModelNotFoundException $exception,
                Request $request
            ) {
                if (! $request->is('api/*')) {
                    return null;
                }

                return ApiResponse::error(
                    $request,
                    'RESOURCE_NOT_FOUND',
                    'Data yang diminta tidak ditemukan.',
                    404
                );
            }
        );

        $exceptions->render(
            function (
                NotFoundHttpException $exception,
                Request $request
            ) {
                if (! $request->is('api/*')) {
                    return null;
                }

                return ApiResponse::error(
                    $request,
                    'RESOURCE_NOT_FOUND',
                    'Resource yang diminta tidak ditemukan.',
                    404
                );
            }
        );

        $exceptions->render(
            function (
                MethodNotAllowedHttpException $exception,
                Request $request
            ) {
                if (! $request->is('api/*')) {
                    return null;
                }

                return ApiResponse::error(
                    $request,
                    'METHOD_NOT_ALLOWED',
                    'Metode request tidak diizinkan.',
                    405
                );
            }
        );

        $exceptions->render(
            function (
                TooManyRequestsHttpException $exception,
                Request $request
            ) {
                if (! $request->is('api/*')) {
                    return null;
                }

                return ApiResponse::error(
                    $request,
                    'RATE_LIMITED',
                    'Terlalu banyak permintaan. Silakan coba kembali.',
                    429
                );
            }
        );

        $exceptions->render(
            function (
                \Throwable $exception,
                Request $request
            ) {
                if (! $request->is('api/*')) {
                    return null;
                }

                return ApiResponse::error(
                    $request,
                    'INTERNAL_ERROR',
                    'Terjadi kesalahan pada sistem.',
                    500
                );
            }
        );
    })
    ->create();
