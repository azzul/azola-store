<?php

namespace App\Http\Middleware;

use App\Models\StockMovement;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membaca kursor stok SEBELUM halaman mengambil data produk. Dengan begitu perubahan
 * stok yang terjadi saat halaman dirender tetap tertangkap oleh polling pertama.
 */
class ShareStockCursor
{
    public function handle(Request $request, Closure $next): Response
    {
        View::share('stockCursor', (int) StockMovement::max('id'));

        return $next($request);
    }
}
