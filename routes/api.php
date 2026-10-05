<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PublicStockController;
use App\Http\Controllers\Api\SyncController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 - dipakai Azola Pos (desktop & Android)
|--------------------------------------------------------------------------
| Semua klien memakai token perangkat (Authorization: Bearer ...).
| Alur sinkronisasi yang disarankan untuk POS:
|   1. POST auth/login          -> simpan token
|   2. GET  products            -> master produk (lalu bertahap dengan ?updated_since)
|   3. GET  sync/stock          -> snapshot stok + kursor, lalu polling ?since=<kursor> tiap 3-5 detik
|   4. POST orders              -> kirim penjualan (uuid dari klien = aman dikirim ulang)
*/

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:30,1');
    Route::get('public/stock-feed', [PublicStockController::class, 'feed'])->middleware('throttle:120,1');

    Route::middleware(['api.token', 'throttle:600,1'])->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::get('categories', [SyncController::class, 'categories']);
        Route::get('products', [SyncController::class, 'products']);
        Route::get('sync/stock', [SyncController::class, 'stock']);

        Route::get('orders', [OrderController::class, 'index']);
        Route::post('orders', [OrderController::class, 'store']);
        Route::get('orders/{uuid}', [OrderController::class, 'show']);
        Route::post('orders/{uuid}/payments', [OrderController::class, 'pay']);

        Route::middleware('role:admin')->group(function () {
            Route::post('orders/{uuid}/cancel', [OrderController::class, 'cancel']);

            Route::post('stock/receive', [AdminController::class, 'receive']);
            Route::post('stock/adjust', [AdminController::class, 'adjust']);
            Route::post('stock/opname', [AdminController::class, 'opname']);

            Route::get('accounts', [AdminController::class, 'accounts']);
            Route::get('journals', [AdminController::class, 'journals']);
            Route::get('reconciliation', [AdminController::class, 'reconciliation']);
        });
    });
});
