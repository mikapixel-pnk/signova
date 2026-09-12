<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\Customer\CustomerController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', HealthController::class);

    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post(
                '/logout',
                [AuthController::class, 'logout']
            );

            Route::middleware(
                'tenant.context'
            )->group(function () {
                Route::get(
                    '/me',
                    [AuthController::class, 'me']
                );
            });
        });
    });

    Route::middleware([
        'auth:sanctum',
        'tenant.context',
    ])->group(function () {
        Route::get(
            '/customers',
            [CustomerController::class, 'index']
        )->middleware(
            'capability:customer.view'
        );

        Route::post(
            '/customers',
            [CustomerController::class, 'store']
        )->middleware(
            'capability:customer.create'
        );

        Route::get(
            '/customers/{customerId}',
            [CustomerController::class, 'show']
        )->middleware(
            'capability:customer.view'
        );

        Route::patch(
            '/customers/{customerId}',
            [CustomerController::class, 'update']
        )->middleware(
            'capability:customer.update'
        );
    });
});
