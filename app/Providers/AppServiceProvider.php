<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Pengaturan toko dari database menimpa config/store.php (nilai bawaan dari .env).
        \App\Support\StoreSettings::apply();

        // Singkatan tampilan untuk halaman admin: @rp($angka), @qty($jumlah)
        \Illuminate\Support\Facades\Blade::directive('rp', fn ($e) => "<?php echo e(\\App\\Support\\Rupiah::format({$e})); ?>");
        \Illuminate\Support\Facades\Blade::directive('qty', fn ($e) => "<?php echo e(\\App\\Support\\Qty::pretty({$e})); ?>");
    }
}
