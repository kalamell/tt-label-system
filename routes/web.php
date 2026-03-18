<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\CustomerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — ระบบจัดการ Label ขนส่ง & สต๊อก FIFO
|--------------------------------------------------------------------------
*/

// ============================================================
// Dashboard
// ============================================================
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

// ============================================================
// Orders (ออเดอร์ / Label)
// ============================================================
Route::prefix('orders')->name('orders.')->group(function () {
    Route::get('/', [OrderController::class, 'index'])->name('index');
    Route::get('/upload', [OrderController::class, 'uploadForm'])->name('upload.form');
    Route::post('/upload', [OrderController::class, 'upload'])->name('upload');
    Route::get('/upload/confirm', [OrderController::class, 'confirmForm'])->name('upload.confirm');
    Route::post('/upload/confirm', [OrderController::class, 'confirmUpload'])->name('upload.confirm.post');
    Route::get('/{order}', [OrderController::class, 'show'])->name('show');

    // Print Labels
    Route::get('/{order}/print', [OrderController::class, 'printLabel'])->name('print');
    Route::post('/print-batch', [OrderController::class, 'printBatch'])->name('print.batch');
    Route::post('/print-overlay', [OrderController::class, 'printOverlay'])->name('print.overlay');
    Route::post('/download-zip', [OrderController::class, 'downloadZip'])->name('download.zip');
    Route::post('/delete-batch', [OrderController::class, 'deleteBatch'])->name('delete.batch');
});

// ============================================================
// สินค้า (Products)
// ============================================================
Route::prefix('products')->name('products.')->group(function () {
    Route::get('/', [ProductController::class, 'index'])->name('index');
    Route::get('/create', [ProductController::class, 'create'])->name('create');
    Route::post('/', [ProductController::class, 'store'])->name('store');
    Route::get('/{product}/edit', [ProductController::class, 'edit'])->name('edit');
    Route::put('/{product}', [ProductController::class, 'update'])->name('update');
    Route::delete('/{product}', [ProductController::class, 'destroy'])->name('destroy');
    Route::delete('/', [ProductController::class, 'bulkDestroy'])->name('bulk-destroy');
});

// ============================================================
// คลังสินค้า / สต๊อก FIFO
// ============================================================
// ============================================================
// รายงาน
// ============================================================
Route::prefix('reports')->name('reports.')->group(function () {
    Route::get('/daily',  [ReportController::class, 'daily'])->name('daily');
    Route::get('/export', [ReportController::class, 'export'])->name('export');
});

// ============================================================
// ลูกค้า (Customers)
// ============================================================
Route::prefix('customers')->name('customers.')->group(function () {
    Route::get('/', [CustomerController::class, 'index'])->name('index');
    Route::get('/{customer}', [CustomerController::class, 'show'])->name('show');
});

Route::prefix('inventory')->name('inventory.')->group(function () {
    Route::get('/', [InventoryController::class, 'index'])->name('index');
    Route::get('/actions', [InventoryController::class, 'actionsForm'])->name('actions');
    Route::get('/receive', [InventoryController::class, 'receiveForm'])->name('receive.form');
    Route::post('/receive', [InventoryController::class, 'receive'])->name('receive');
    Route::get('/transactions', [InventoryController::class, 'transactions'])->name('transactions');
    Route::get('/issue', [InventoryController::class, 'issueForm'])->name('issue.form');
    Route::post('/issue', [InventoryController::class, 'issue'])->name('issue');
    Route::get('/{product}', [InventoryController::class, 'show'])->name('show');
    Route::post('/lots/{lot}/adjust', [InventoryController::class, 'adjust'])->name('adjust');
});
