<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PublicDocumentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ServiceOrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| autogest_nicola - rotas web
|--------------------------------------------------------------------------
*/

// -------------------------------------------------------------------------
// Autenticação
// -------------------------------------------------------------------------
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.attempt');
});

Route::post('logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// -------------------------------------------------------------------------
// Verificação pública (destino do QR Code dos documentos)
// -------------------------------------------------------------------------
Route::get('d/{uuid}', [PublicDocumentController::class, 'verify'])->name('documents.public');
Route::get('d/{uuid}/pdf', [PublicDocumentController::class, 'pdf'])->name('documents.public.pdf');

// -------------------------------------------------------------------------
// Área autenticada
// -------------------------------------------------------------------------
Route::middleware('auth')->group(function () {

    Route::get('/', DashboardController::class)->name('dashboard');

    // --- Documentos fiscais ---------------------------------------------
    Route::prefix('documentos')->name('documents.')->group(function () {
        Route::middleware('permission:documents.view')->group(function () {
            Route::get('/', [DocumentController::class, 'index'])->name('index');
            Route::get('{document}', [DocumentController::class, 'show'])->name('show');
        });

        Route::middleware('permission:documents.create')->group(function () {
            Route::get('emitir/novo', [DocumentController::class, 'create'])->name('create');
            Route::post('/', [DocumentController::class, 'store'])->name('store');
        });

        Route::middleware('permission:documents.print')->group(function () {
            Route::get('{document}/pdf/{format}', [DocumentController::class, 'pdf'])->name('pdf');
            Route::post('{document}/imprimir/{format}', [DocumentController::class, 'print'])->name('print');
        });

        Route::post('{document}/whatsapp', [DocumentController::class, 'sendWhatsApp'])
            ->middleware('permission:whatsapp.send')
            ->name('whatsapp');

        Route::post('{document}/anular', [DocumentController::class, 'cancel'])
            ->middleware('permission:documents.cancel')
            ->name('cancel');
    });

    // --- Oficina / Ordens de Serviço ------------------------------------
    Route::prefix('oficina')->name('service-orders.')->group(function () {
        Route::middleware('permission:workshop.view')->group(function () {
            Route::get('/', [ServiceOrderController::class, 'index'])->name('index');
            Route::get('{serviceOrder}', [ServiceOrderController::class, 'show'])->name('show');
        });

        Route::middleware('permission:workshop.manage')->group(function () {
            Route::get('nova/abrir', [ServiceOrderController::class, 'create'])->name('create');
            Route::post('/', [ServiceOrderController::class, 'store'])->name('store');
            Route::post('{serviceOrder}/estado', [ServiceOrderController::class, 'transition'])->name('transition');
        });

        Route::post('{serviceOrder}/faturar', [ServiceOrderController::class, 'invoice'])
            ->middleware('permission:workshop.invoice')
            ->name('invoice');
    });

    // --- Clientes -------------------------------------------------------
    Route::middleware('permission:customers.view')->group(function () {
        Route::get('clientes', [CustomerController::class, 'index'])->name('customers.index');
    });
    Route::post('clientes', [CustomerController::class, 'store'])
        ->middleware('permission:customers.manage')
        ->name('customers.store');

    // --- Produtos / Stock -----------------------------------------------
    Route::middleware('permission:products.view')->group(function () {
        Route::get('produtos', [ProductController::class, 'index'])->name('products.index');
    });
    Route::post('produtos', [ProductController::class, 'store'])
        ->middleware('permission:products.manage')
        ->name('products.store');

    // --- Relatórios -----------------------------------------------------
    Route::middleware('permission:reports.view')->group(function () {
        Route::get('relatorios', [ReportController::class, 'index'])->name('reports.index');
        Route::get('relatorios/{report}', [ReportController::class, 'show'])
            ->where('report', 'faturacao|faturacao-utilizador|impostos|produtividade|stock|conta-corrente|caixa')
            ->name('reports.show');

        Route::middleware('permission:reports.export')->group(function () {
            Route::get('relatorios/{report}/pdf', [ReportController::class, 'pdf'])
                ->where('report', 'faturacao|faturacao-utilizador|impostos|produtividade|stock|conta-corrente|caixa')
                ->name('reports.pdf');
            Route::get('relatorios/{report}/excel', [ReportController::class, 'excel'])
                ->where('report', 'faturacao|faturacao-utilizador|impostos|produtividade|stock|conta-corrente|caixa')
                ->name('reports.excel');
        });
    });

    // --- Fisco (SAF-T AO) -----------------------------------------------
    Route::get('saft/exportar', [ReportController::class, 'saft'])
        ->middleware('permission:saft.export')
        ->name('saft.export');
});
