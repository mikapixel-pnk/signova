<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\Public\V1\Quotation\PublicQuotationController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\Finance\CashAccountController;
use App\Http\Controllers\Api\V1\Finance\ExpenseController;
use App\Http\Controllers\Api\V1\Finance\FinanceSummaryController;
use App\Http\Controllers\Api\V1\Finance\IncomeController;
use App\Http\Controllers\Api\V1\Invoice\InvoiceController;
use App\Http\Controllers\Api\V1\Payment\PaymentController;
use App\Http\Controllers\Api\V1\Settings\BusinessProfileController;
use App\Http\Controllers\Api\V1\Settings\DocumentSettingController;
use App\Http\Controllers\Api\V1\Settings\InvoiceTemplateSettingController;
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

        Route::post(
            '/password/forgot',
            [AuthController::class, 'forgotPassword']
        )->middleware(
            'throttle:auth.password.forgot'
        );

        Route::post(
            '/password/verify',
            [AuthController::class, 'verifyPasswordResetOtp']
        )->middleware(
            'throttle:auth.password.verify'
        );

        Route::post(
            '/password/reset',
            [AuthController::class, 'resetPassword']
        )->middleware(
            'throttle:auth.password.reset'
        );

        Route::middleware('auth:sanctum')->group(function () {
            Route::get(
                '/context',
                [AuthController::class, 'context']
            );

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
                )->middleware(
                    'business.context'
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
        )->middleware([
            'business.context',
            'capability:customer.view',
        ]);

        Route::post(
            '/customers',
            [CustomerController::class, 'store']
        )->middleware([
            'business.context',
            'capability:customer.create',
        ]);

        Route::get(
            '/customers/{customerId}',
            [CustomerController::class, 'show']
        )->middleware([
            'business.context',
            'capability:customer.view',
        ]);

        Route::patch(
            '/customers/{customerId}',
            [CustomerController::class, 'update']
        )->middleware([
            'business.context',
            'capability:customer.update',
        ]);


        /*
        |--------------------------------------------------------------------------
        | Penawaran
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/quotations',
            [QuotationController::class, 'index']
        )->middleware([
            'business.context',
            'capability:quotation.view',
        ]);

        Route::post(
            '/quotations',
            [QuotationController::class, 'store']
        )->middleware([
            'business.context',
            'capability:quotation.create',
        ]);

        Route::get(
            '/quotations/{quotationId}/pdf',
            [QuotationController::class, 'pdf']
        )->middleware([
            'business.context',
            'capability:quotation.view',
        ]);

        Route::get(
            '/quotations/{quotationId}',
            [QuotationController::class, 'show']
        )->middleware([
            'business.context',
            'capability:quotation.view',
        ]);


        Route::patch(
            '/quotations/{quotationId}',
            [QuotationController::class, 'update']
        )->middleware([
            'business.context',
            'capability:quotation.update',
        ]);


        Route::post(
            '/quotations/{quotationId}/versions',
            [QuotationController::class, 'storeVersion']
        )->middleware([
            'business.context',
            'capability:quotation.update',
        ]);


        Route::post(
            '/quotations/{quotationId}/actions/send',
            [QuotationController::class, 'send']
        )->middleware([
            'business.context',
            'capability:quotation.issue',
        ]);


        Route::post(
            '/quotations/{quotationId}/actions/manual-decision',
            [QuotationController::class, 'manualDecision']
        )->middleware([
            'business.context',
            'capability:quotation.issue',
        ]);


        Route::post(
            '/quotations/{quotationId}/actions/issue-public-link',
            [QuotationController::class, 'issuePublicLink']
        )->middleware([
            'business.context',
            'capability:quotation.issue',
        ]);


        Route::post(
            '/quotations/{quotationId}/actions/create-invoice',
            [QuotationController::class, 'createInvoice']
        )->middleware([
            'business.context',
            'capability:invoice.create',
        ]);

        Route::post(
            '/quotations/{quotationId}/actions/cancel',
            [QuotationController::class, 'cancel']
        )->middleware([
            'business.context',
            'capability:quotation.issue',
        ]);


        /*
        |--------------------------------------------------------------------------
        | Tagihan
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/invoices',
            [InvoiceController::class, 'index']
        )->middleware([
            'business.context',
            'capability:invoice.view',
        ]);

        Route::post(
            '/invoices',
            [InvoiceController::class, 'store']
        )->middleware([
            'business.context',
            'capability:invoice.create',
        ]);

        Route::patch(
            '/invoices/{invoiceId}',
            [InvoiceController::class, 'update']
        )->middleware([
            'business.context',
            'capability:invoice.update',
        ]);

        Route::post(
            '/invoices/{invoiceId}/actions/issue',
            [InvoiceController::class, 'issue']
        )->middleware([
            'business.context',
            'capability:invoice.issue',
        ]);


        Route::post(
            '/invoices/{invoiceId}/actions/void',
            [InvoiceController::class, 'void']
        )->middleware([
            'business.context',
            'capability:invoice.void',
        ]);

        Route::get(
            '/invoices/{invoiceId}/pdf',
            [InvoiceController::class, 'pdf']
        )->middleware([
            'business.context',
            'capability:invoice.view',
        ]);

        Route::get(
            '/invoices/{invoiceId}',
            [InvoiceController::class, 'show']
        )->middleware([
            'business.context',
            'capability:invoice.view',
        ]);


        /*
        |--------------------------------------------------------------------------
        | Pembayaran
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/invoices/{invoiceId}/payments',
            [
                PaymentController::class,
                'invoicePayments',
            ]
        )->middleware([
            'business.context',
            'capability:payment.view',
        ]);

        Route::post(
            '/invoices/{invoiceId}/actions/record-payment',
            [
                PaymentController::class,
                'recordForInvoice',
            ]
        )->middleware([
            'business.context',
            'capability:payment.record',
            'capability:payment.verify',
        ]);

        Route::get(
            '/payments',
            [PaymentController::class, 'index']
        )->middleware([
            'business.context',
            'capability:payment.view',
        ]);

        Route::post(
            '/payments',
            [PaymentController::class, 'store']
        )->middleware([
            'business.context',
            'capability:payment.record',
        ]);

        Route::post(
            '/payments/{paymentId}/allocations',
            [PaymentController::class, 'allocate']
        )->middleware([
            'business.context',
            'capability:payment.verify',
        ]);

        Route::post(
            '/payments/{paymentId}/actions/verify',
            [PaymentController::class, 'verify']
        )->middleware([
            'business.context',
            'capability:payment.verify',
        ]);

        Route::post(
            '/payments/{paymentId}/actions/reverse',
            [PaymentController::class, 'reverse']
        )->middleware([
            'business.context',
            'capability:payment.reverse',
        ]);

        Route::post(
            '/payments/{paymentId}/actions/reject',
            [PaymentController::class, 'reject']
        )->middleware([
            'business.context',
            'capability:payment.verify',
        ]);

        Route::get(
            '/payments/{paymentId}',
            [PaymentController::class, 'show']
        )->middleware([
            'business.context',
            'capability:payment.view',
        ]);

        Route::post(
            '/payments/{paymentId}/evidence',
            [PaymentController::class, 'uploadEvidence']
        )->middleware([
            'business.context',
            'capability:payment.record',
        ]);

        Route::get(
            '/payments/{paymentId}/evidence',
            [PaymentController::class, 'evidence']
        )->middleware([
            'business.context',
            'capability:payment.view',
        ]);

        Route::delete(
            '/payments/{paymentId}/evidence',
            [PaymentController::class, 'destroyEvidence']
        )->middleware([
            'business.context',
            'capability:payment.record',
        ]);


        /*
        |--------------------------------------------------------------------------
        | Ringkasan Keuangan
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/finance/summary',
            FinanceSummaryController::class
        )->middleware([
            'business.context',
            'capability:finance.summary.view',
        ]);


        /*
        |--------------------------------------------------------------------------
        | Kas & Bank
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/finance/cash-accounts',
            [CashAccountController::class, 'index']
        )->middleware([
            'business.context',
            'capability:finance.cash_bank.view',
        ]);

        Route::post(
            '/finance/cash-accounts',
            [CashAccountController::class, 'store']
        )->middleware([
            'business.context',
            'capability:finance.cash_bank.manage',
        ]);

        Route::get(
            '/finance/cash-accounts/{cashAccountId}',
            [CashAccountController::class, 'show']
        )->middleware([
            'business.context',
            'capability:finance.cash_bank.view',
        ]);

        Route::patch(
            '/finance/cash-accounts/{cashAccountId}',
            [CashAccountController::class, 'update']
        )->middleware([
            'business.context',
            'capability:finance.cash_bank.manage',
        ]);

        Route::delete(
            '/finance/cash-accounts/{cashAccountId}',
            [CashAccountController::class, 'destroy']
        )->middleware([
            'business.context',
            'capability:finance.cash_bank.manage',
        ]);

        Route::post(
            '/finance/cash-accounts/{cashAccountId}/actions/activate',
            [CashAccountController::class, 'activate']
        )->middleware([
            'business.context',
            'capability:finance.cash_bank.manage',
        ]);

        Route::post(
            '/finance/cash-accounts/{cashAccountId}/actions/deactivate',
            [CashAccountController::class, 'deactivate']
        )->middleware([
            'business.context',
            'capability:finance.cash_bank.manage',
        ]);

        Route::post(
            '/finance/cash-accounts/{cashAccountId}/actions/set-default',
            [CashAccountController::class, 'setDefault']
        )->middleware([
            'business.context',
            'capability:finance.cash_bank.manage',
        ]);


        /*
        |--------------------------------------------------------------------------
        | Pemasukan
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/finance/incomes',
            [IncomeController::class, 'index']
        )->middleware([
            'business.context',
            'capability:finance.income.view',
        ]);

        Route::post(
            '/finance/incomes',
            [IncomeController::class, 'store']
        )->middleware([
            'business.context',
            'capability:finance.income.manage',
        ]);

        Route::get(
            '/finance/incomes/{incomeId}',
            [IncomeController::class, 'show']
        )->middleware([
            'business.context',
            'capability:finance.income.view',
        ]);

        Route::patch(
            '/finance/incomes/{incomeId}',
            [IncomeController::class, 'update']
        )->middleware([
            'business.context',
            'capability:finance.income.manage',
        ]);

        Route::delete(
            '/finance/incomes/{incomeId}',
            [IncomeController::class, 'destroy']
        )->middleware([
            'business.context',
            'capability:finance.income.manage',
        ]);

        Route::post(
            '/finance/incomes/{incomeId}/actions/post',
            [IncomeController::class, 'post']
        )->middleware([
            'business.context',
            'capability:finance.income.manage',
        ]);

        Route::post(
            '/finance/incomes/{incomeId}/actions/void',
            [IncomeController::class, 'void']
        )->middleware([
            'business.context',
            'capability:finance.income.manage',
        ]);


        /*
        |--------------------------------------------------------------------------
        | Pengeluaran
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/finance/expenses',
            [ExpenseController::class, 'index']
        )->middleware([
            'business.context',
            'capability:finance.expense.view',
        ]);

        Route::post(
            '/finance/expenses',
            [ExpenseController::class, 'store']
        )->middleware([
            'business.context',
            'capability:finance.expense.manage',
        ]);

        Route::get(
            '/finance/expenses/{expenseId}',
            [ExpenseController::class, 'show']
        )->middleware([
            'business.context',
            'capability:finance.expense.view',
        ]);

        Route::patch(
            '/finance/expenses/{expenseId}',
            [ExpenseController::class, 'update']
        )->middleware([
            'business.context',
            'capability:finance.expense.manage',
        ]);

        Route::delete(
            '/finance/expenses/{expenseId}',
            [ExpenseController::class, 'destroy']
        )->middleware([
            'business.context',
            'capability:finance.expense.manage',
        ]);

        Route::post(
            '/finance/expenses/{expenseId}/actions/post',
            [ExpenseController::class, 'post']
        )->middleware([
            'business.context',
            'capability:finance.expense.manage',
        ]);

        Route::post(
            '/finance/expenses/{expenseId}/actions/void',
            [ExpenseController::class, 'void']
        )->middleware([
            'business.context',
            'capability:finance.expense.manage',
        ]);


        /*
        |--------------------------------------------------------------------------
        | Pengaturan Dokumen & Branding
        |--------------------------------------------------------------------------
        */

        /*
        |--------------------------------------------------------------------------
        | Profil Usaha
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/settings/business-profile',
            [BusinessProfileController::class, 'show']
        )->middleware([
            'business.context',
            'capability:settings.view',
        ]);

        Route::patch(
            '/settings/business-profile',
            [BusinessProfileController::class, 'update']
        )->middleware([
            'business.context',
            'capability:settings.manage',
        ]);


        Route::post(
            '/settings/business-profile/logo',
            [BusinessProfileController::class, 'uploadLogo']
        )->middleware([
            'business.context',
            'capability:settings.manage',
        ]);

        Route::get(
            '/settings/business-profile/logo',
            [BusinessProfileController::class, 'logo']
        )->middleware([
            'business.context',
            'capability:settings.view',
        ]);

        Route::delete(
            '/settings/business-profile/logo',
            [BusinessProfileController::class, 'destroyLogo']
        )->middleware([
            'business.context',
            'capability:settings.manage',
        ]);


        Route::get(
            '/settings/document',
            [DocumentSettingController::class, 'show']
        )->middleware([
            'business.context',
            'capability:settings.view',
        ]);

        Route::patch(
            '/settings/document',
            [DocumentSettingController::class, 'update']
        )->middleware([
            'business.context',
            'capability:settings.manage',
        ]);

        Route::post(
            '/settings/document/signature',
            [DocumentSettingController::class, 'uploadSignature']
        )->middleware([
            'business.context',
            'capability:settings.manage',
        ]);

        Route::get(
            '/settings/document/signature',
            [DocumentSettingController::class, 'signature']
        )->middleware([
            'business.context',
            'capability:settings.view',
        ]);

        Route::delete(
            '/settings/document/signature',
            [DocumentSettingController::class, 'destroySignature']
        )->middleware([
            'business.context',
            'capability:settings.manage',
        ]);


        /*
        |--------------------------------------------------------------------------
        | Template Invoice
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/settings/invoice-templates',
            [
                InvoiceTemplateSettingController::class,
                'index',
            ]
        )->middleware([
            'business.context',
            'capability:settings.view',
        ]);

        Route::get(
            '/settings/invoice-templates/{templateKey}/preview',
            [
                InvoiceTemplateSettingController::class,
                'preview',
            ]
        )->middleware([
            'business.context',
            'capability:settings.view',
        ]);


        Route::patch(
            '/settings/invoice-templates',
            [
                InvoiceTemplateSettingController::class,
                'update',
            ]
        )->middleware([
            'business.context',
            'capability:settings.manage',
        ]);


        /*
        |--------------------------------------------------------------------------
        | Pengaturan Pembayaran
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/settings/payment',
            [PaymentSettingController::class, 'show']
        )->middleware([
            'business.context',
            'capability:settings.view',
        ]);

        Route::patch(
            '/settings/payment',
            [PaymentSettingController::class, 'update']
        )->middleware([
            'business.context',
            'capability:settings.manage',
        ]);

        Route::post(
            '/settings/payment/static-qr',
            [PaymentSettingController::class, 'uploadStaticQr']
        )->middleware([
            'business.context',
            'capability:settings.manage',
        ]);

        Route::get(
            '/settings/payment/static-qr',
            [PaymentSettingController::class, 'staticQr']
        )->middleware([
            'business.context',
            'capability:settings.view',
        ]);

        Route::delete(
            '/settings/payment/static-qr',
            [PaymentSettingController::class, 'destroyStaticQr']
        )->middleware([
            'business.context',
            'capability:settings.manage',
        ]);


        /*
        |--------------------------------------------------------------------------
        | Catalog - Barang & Jasa
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/catalog/items',
            [CatalogItemController::class, 'index']
        )->middleware([
            'business.context',
            'capability:catalog.view',
        ]);

        Route::post(
            '/catalog/items',
            [CatalogItemController::class, 'store']
        )->middleware([
            'business.context',
            'capability:catalog.manage',
        ]);

        Route::get(
            '/catalog/items/{itemId}',
            [CatalogItemController::class, 'show']
        )->middleware([
            'business.context',
            'capability:catalog.view',
        ]);

        Route::patch(
            '/catalog/items/{itemId}',
            [CatalogItemController::class, 'update']
        )->middleware([
            'business.context',
            'capability:catalog.manage',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Catalog - Kategori
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/catalog/categories',
            [CatalogCategoryController::class, 'index']
        )->middleware([
            'business.context',
            'capability:catalog.view',
        ]);

        Route::post(
            '/catalog/categories',
            [CatalogCategoryController::class, 'store']
        )->middleware([
            'business.context',
            'capability:catalog.manage',
        ]);

        Route::patch(
            '/catalog/categories/{categoryId}',
            [CatalogCategoryController::class, 'update']
        )->middleware([
            'business.context',
            'capability:catalog.manage',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Master Satuan
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/units',
            [UnitController::class, 'index']
        )->middleware([
            'business.context',
            'capability:catalog.view',
        ]);

        Route::post(
            '/units',
            [UnitController::class, 'store']
        )->middleware([
            'business.context',
            'capability:catalog.manage',
        ]);

        Route::patch(
            '/units/{unitId}',
            [UnitController::class, 'update']
        )->middleware([
            'business.context',
            'capability:catalog.manage',
        ]);
    });
});
