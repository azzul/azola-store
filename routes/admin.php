<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\MessageController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeviceController;
use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Dashboard admin (login sesi, khusus role admin)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('masuk', [AuthController::class, 'show'])->name('login');
    Route::post('masuk', [AuthController::class, 'login'])->name('login.store');

    Route::middleware(['auth', 'role:admin'])->group(function () {
        Route::post('keluar', [AuthController::class, 'logout'])->name('logout');

        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('stok', [DashboardController::class, 'stock'])->name('stock');
        Route::get('stok/feed', [DashboardController::class, 'stockFeed'])->name('stock.feed');

        Route::get('produk', [ProductController::class, 'index'])->name('products.index');
        Route::get('produk/baru', [ProductController::class, 'create'])->name('products.create');
        Route::post('produk', [ProductController::class, 'store'])->name('products.store');
        Route::get('produk/{product}', [ProductController::class, 'edit'])->name('products.edit');
        Route::put('produk/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::post('produk/{product}/stok-masuk', [ProductController::class, 'receive'])->name('products.receive');
        Route::post('produk/{product}/penyesuaian', [ProductController::class, 'adjust'])->name('products.adjust');
        Route::post('produk/{product}/opname', [ProductController::class, 'opname'])->name('products.opname');

        Route::get('kategori', [CategoryController::class, 'index'])->name('categories.index');
        Route::post('kategori', [CategoryController::class, 'store'])->name('categories.store');
        Route::delete('kategori/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

        Route::get('pesanan', [OrderController::class, 'index'])->name('orders.index');
        Route::get('pesanan/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('pesanan/{order}/bayar', [OrderController::class, 'pay'])->name('orders.pay');
        Route::post('pesanan/{order}/selesai', [OrderController::class, 'complete'])->name('orders.complete');
        Route::post('pesanan/{order}/batal', [OrderController::class, 'cancel'])->name('orders.cancel');

        Route::get('jurnal', [FinanceController::class, 'journals'])->name('journals.index');
        Route::get('jurnal/{journal}', [FinanceController::class, 'journal'])->name('journals.show');
        Route::get('laporan', [FinanceController::class, 'report'])->name('report');
        Route::get('rekonsiliasi', [FinanceController::class, 'reconcile'])->name('reconcile');

        Route::get('ulasan', [ReviewController::class, 'index'])->name('reviews.index');
        Route::post('ulasan', [ReviewController::class, 'store'])->name('reviews.store');
        Route::post('ulasan/{review}/tampil', [ReviewController::class, 'toggle'])->name('reviews.toggle');
        Route::delete('ulasan/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');

        Route::get('klien', [ClientController::class, 'index'])->name('clients.index');
        Route::post('klien', [ClientController::class, 'store'])->name('clients.store');
        Route::post('klien/{client}/tampil', [ClientController::class, 'toggle'])->name('clients.toggle');
        Route::delete('klien/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');

        Route::get('pesan', [MessageController::class, 'index'])->name('messages.index');
        Route::get('pesan/{message}', [MessageController::class, 'show'])->name('messages.show');
        Route::delete('pesan/{message}', [MessageController::class, 'destroy'])->name('messages.destroy');

        Route::get('perangkat', [DeviceController::class, 'index'])->name('devices.index');
        Route::post('perangkat', [DeviceController::class, 'store'])->name('devices.store');
        Route::delete('perangkat/{token}', [DeviceController::class, 'revoke'])->name('devices.revoke');
    });
});
