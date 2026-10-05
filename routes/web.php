<?php

use App\Http\Controllers\Store\CartController;
use App\Http\Controllers\Store\CheckoutController;
use App\Http\Controllers\Store\HomeController;
use App\Http\Controllers\Store\PageController;
use App\Http\Controllers\Store\ReviewController;
use App\Http\Controllers\Store\SeoController;
use App\Http\Controllers\Store\ShopController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Toko online (publik)
|--------------------------------------------------------------------------
*/
Route::middleware('stock.cursor')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');

    Route::get('/produk', [ShopController::class, 'index'])->name('shop.index');
    Route::get('/produk/{product:slug}', [ShopController::class, 'show'])->name('shop.product');
    Route::get('/kategori/{category:slug}', [ShopController::class, 'category'])->name('shop.category');

    Route::get('/keranjang', [CartController::class, 'index'])->name('cart.index');
    Route::post('/keranjang/tambah/{product:slug}', [CartController::class, 'add'])->name('cart.add');
    Route::patch('/keranjang', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/keranjang/{product}', [CartController::class, 'remove'])->whereNumber('product')->name('cart.remove');

    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');
    Route::get('/tentang-kami', [PageController::class, 'about'])->name('about');
    Route::get('/kontak', [PageController::class, 'contact'])->name('contact');
    Route::post('/kontak', [PageController::class, 'contactSend'])->middleware('throttle:5,10')->name('contact.send');
    Route::get('/ulasan', [ReviewController::class, 'index'])->name('reviews');
    Route::post('/ulasan', [ReviewController::class, 'store'])->middleware('throttle:5,60')->name('reviews.store');
    Route::get('/klien', [PageController::class, 'clients'])->name('clients');
    Route::get('/pertanyaan-umum', [PageController::class, 'faq'])->name('faq');
    Route::get('/kebijakan-privasi', [PageController::class, 'privacy'])->name('privacy');
    Route::get('/syarat-ketentuan', [PageController::class, 'terms'])->name('terms');
    Route::get('/pengembalian', [PageController::class, 'returns'])->name('returns');

    Route::get('/pesanan/{uuid}', [CheckoutController::class, 'order'])->whereUuid('uuid')->name('order.show');
});

Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');

require __DIR__.'/admin.php';
