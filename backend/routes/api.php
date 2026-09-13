<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\Public\V1\Quotation\PublicQuotationController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\Invoice\InvoiceController;
use App\Http\Controllers\Api\V1\Payment\PaymentController;
use App\Http\Controllers\Api\V1\Settings\DocumentSettingController;
use App\Http\Controllers\Api\V1\Settings\PaymentSettingController;
use App\Http\Controllers\Api\V1\Quotation\QuotationController;
use App\Http\Controllers\Api\V1\Customer\CustomerController;
use App\Http\Controllers\Api\V1\Catalog\CatalogCategoryController;
use App\Http\Controllers\Api\V1\Catalog\CatalogItemController;
use App\Http\Controllers\Api\V1\Catalog\UnitController;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| Public quotation
|--------------------------------------------------------------------------
|
| Token-based customer access. No auth / tenant context.
| GET must remain side-effect free.
|
*/

Route::prefix('public/v1')->group(function () {
    Route::get(
        '/quotations/{token}',
        [PublicQuotationController::class, 'show']
    );

    Route::post(
        '/quotations/{token}/actions/view',
        [PublicQuotationController::class, 'view']
    );


    Route::post(
        '/quotations/{token}/approve',
        [PublicQuotationController::class, 'approve']
    );

    Route::post(
        '/quotations/{token}/reject',
        [PublicQuotationController::class, 'reject']
    );
});

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
            '/quotations/{quotationId}/pdf',
            [QuotationController::class, 'pdf']
        )->middleware(
            'capability:quotation.view'
        );

        Route::get(
            '/quotations/{quotationId}',
            [QuotationController::class, 'show']
        )->middleware(
            'capability:quotation.view'
        );


        Route::patch(
            '/quotations/{quotationId}',
            [QuotationController::class, 'update']
        )->middleware(
            'capability:quotation.update'
        );


        Route::post(
            '/quotations/{quotationId}/versions',
            [QuotationController::class, 'storeVersion']
        )->middleware(
            'capability:quotation.update'
        );


        Route::post(
            '/quotations/{quotationId}/actions/send',
            [QuotationController::class, 'send']
        )->middleware(
            'capability:quotation.issue'
        );


        Route::post(
            '/quotations/{quotationId}/actions/create-invoice',
            [QuotationController::class, 'createInvoice']
        )->middleware(
            'capability:invoice.create'
        );

        Route::post(
            '/quotations/{quotationId}/actions/cancel',
            [QuotationController::class, 'cancel']
        )->middleware(
            'capability:quotation.issue'
        );


        /*
        |--------------------------------------------------------------------------
        | Tagihan
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/invoices',
            [InvoiceController::class, 'index']
        )->middleware(
            'capability:invoice.view'
        );

        Route::post(
            '/invoices/{invoiceId}/actions/issue',
            [InvoiceController::class, 'issue']
        )->middleware(
            'capability:invoice.issue'
        );


        Route::post(
            '/invoices/{invoiceId}/actions/void',
            [InvoiceController::class, 'void']
        )->middleware(
            'capability:invoice.void'
        );

        Route::get(
            '/invoices/{invoiceId}/pdf',
            [InvoiceController::class, 'pdf']
        )->middleware(
            'capability:invoice.view'
        );

        Route::get(
            '/invoices/{invoiceId}',
            [InvoiceController::class, 'show']
        )->middleware(
            'capability:invoice.view'
        );


        /*
        |--------------------------------------------------------------------------
        | Pembayaran
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/payments',
            [PaymentController::class, 'index']
        )->middleware(
            'capability:payment.view'
        );

        Route::post(
            '/payments',
            [PaymentController::class, 'store']
        )->middleware(
            'capability:payment.record'
        );

        Route::post(
            '/payments/{paymentId}/allocations',
            [PaymentController::class, 'allocate']
        )->middleware(
            'capability:payment.verify'
        );

        Route::post(
            '/payments/{paymentId}/actions/verify',
            [PaymentController::class, 'verify']
        )->middleware(
            'capability:payment.verify'
        );

        Route::post(
            '/payments/{paymentId}/actions/reject',
            [PaymentController::class, 'reject']
        )->middleware(
            'capability:payment.verify'
        );

        Route::get(
            '/payments/{paymentId}',
            [PaymentController::class, 'show']
        )->middleware(
            'capability:payment.view'
        );

        Route::post(
            '/payments/{paymentId}/evidence',
            [PaymentController::class, 'uploadEvidence']
        )->middleware(
            'capability:payment.record'
        );

        Route::get(
            '/payments/{paymentId}/evidence',
            [PaymentController::class, 'evidence']
        )->middleware(
            'capability:payment.view'
        );

        Route::delete(
            '/payments/{paymentId}/evidence',
            [PaymentController::class, 'destroyEvidence']
        )->middleware(
            'capability:payment.record'
        );


        /*
        |--------------------------------------------------------------------------
        | Pengaturan Dokumen & Branding
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/settings/document',
            [DocumentSettingController::class, 'show']
        )->middleware(
            'capability:settings.view'
        );

        Route::patch(
            '/settings/document',
            [DocumentSettingController::class, 'update']
        )->middleware(
            'capability:settings.manage'
        );

        Route::post(
            '/settings/document/signature',
            [DocumentSettingController::class, 'uploadSignature']
        )->middleware(
            'capability:settings.manage'
        );

        Route::get(
            '/settings/document/signature',
            [DocumentSettingController::class, 'signature']
        )->middleware(
            'capability:settings.view'
        );

        Route::delete(
            '/settings/document/signature',
            [DocumentSettingController::class, 'destroySignature']
        )->middleware(
            'capability:settings.manage'
        );


        /*
        |--------------------------------------------------------------------------
        | Pengaturan Pembayaran
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/settings/payment',
            [PaymentSettingController::class, 'show']
        )->middleware(
            'capability:settings.view'
        );

        Route::patch(
            '/settings/payment',
            [PaymentSettingController::class, 'update']
        )->middleware(
            'capability:settings.manage'
        );

        Route::post(
            '/settings/payment/static-qr',
            [PaymentSettingController::class, 'uploadStaticQr']
        )->middleware(
            'capability:settings.manage'
        );

        Route::get(
            '/settings/payment/static-qr',
            [PaymentSettingController::class, 'staticQr']
        )->middleware(
            'capability:settings.view'
        );

        Route::delete(
            '/settings/payment/static-qr',
            [PaymentSettingController::class, 'destroyStaticQr']
        )->middleware(
            'capability:settings.manage'
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
