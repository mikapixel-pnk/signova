<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\Quotation\QuotationController;
use App\Http\Controllers\Api\V1\Customer\CustomerController;
use App\Http\Controllers\Api\V1\Catalog\CatalogCategoryController;
use App\Http\Controllers\Api\V1\Catalog\CatalogItemController;
use App\Http\Controllers\Api\V1\Catalog\UnitController;
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


        /*
        |--------------------------------------------------------------------------
        | Penawaran
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/quotations',
            [QuotationController::class, 'index']
        )->middleware(
            'capability:quotation.view'
        );

        Route::post(
            '/quotations',
            [QuotationController::class, 'store']
        )->middleware(
            'capability:quotation.create'
        );

        Route::get(
            '/quotations/{quotationId}',
            [QuotationController::class, 'show']
        )->middleware(
            'capability:quotation.view'
        );


        /*
        |--------------------------------------------------------------------------
        | Catalog - Barang & Jasa
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/catalog/items',
            [CatalogItemController::class, 'index']
        )->middleware(
            'capability:catalog.view'
        );

        Route::post(
            '/catalog/items',
            [CatalogItemController::class, 'store']
        )->middleware(
            'capability:catalog.manage'
        );

        Route::get(
            '/catalog/items/{itemId}',
            [CatalogItemController::class, 'show']
        )->middleware(
            'capability:catalog.view'
        );

        Route::patch(
            '/catalog/items/{itemId}',
            [CatalogItemController::class, 'update']
        )->middleware(
            'capability:catalog.manage'
        );

        /*
        |--------------------------------------------------------------------------
        | Catalog - Kategori
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/catalog/categories',
            [CatalogCategoryController::class, 'index']
        )->middleware(
            'capability:catalog.view'
        );

        Route::post(
            '/catalog/categories',
            [CatalogCategoryController::class, 'store']
        )->middleware(
            'capability:catalog.manage'
        );

        Route::patch(
            '/catalog/categories/{categoryId}',
            [CatalogCategoryController::class, 'update']
        )->middleware(
            'capability:catalog.manage'
        );

        /*
        |--------------------------------------------------------------------------
        | Master Satuan
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/units',
            [UnitController::class, 'index']
        )->middleware(
            'capability:catalog.view'
        );
    });
});
