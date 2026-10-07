<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\ArticleController;
use App\Http\Controllers\Admin\CatalogController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\EtalaseController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\MessageController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeviceController;
use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\ConversionController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\LookupController;
use App\Http\Controllers\Admin\PriceController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\UnitController;
use App\Http\Controllers\Admin\VariationController;
use App\Http\Controllers\Admin\PayableController;
use App\Http\Controllers\Admin\PurchaseController;
use App\Http\Controllers\Admin\PurchaseReturnController;
use App\Http\Controllers\Admin\TransferController;
use App\Http\Controllers\Admin\WarehouseController;
use App\Http\Controllers\Admin\AdjustmentController;
use App\Http\Controllers\Admin\AdjustmentJournalController;
use App\Http\Controllers\Admin\AssetController;
use App\Http\Controllers\Admin\BalanceController;
use App\Http\Controllers\Admin\CardController;
use App\Http\Controllers\Admin\CashController;
use App\Http\Controllers\Admin\DepositController;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\OnlineOrderController;
use App\Http\Controllers\Admin\OpnameController;
use App\Http\Controllers\Admin\OverpaymentController;
use App\Http\Controllers\Admin\RecapController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SaleController;
use App\Http\Controllers\Admin\SaleReturnController;
use App\Http\Controllers\Admin\StockReportController;
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

        Route::get('katalog', [CatalogController::class, 'index'])->name('catalog.index');
        Route::get('katalog/baru', [CatalogController::class, 'create'])->name('catalog.create');
        Route::post('katalog', [CatalogController::class, 'store'])->name('catalog.store');
        Route::get('katalog/{group}', [CatalogController::class, 'edit'])->name('catalog.edit');
        Route::put('katalog/{group}', [CatalogController::class, 'update'])->name('catalog.update');
        Route::post('katalog/{group}/variasi', [CatalogController::class, 'storeVariant'])->name('catalog.variants.store');
        Route::post('katalog/{group}/foto', [CatalogController::class, 'uploadImages'])->name('catalog.images.store');
        Route::put('katalog/{group}/foto/{image}', [CatalogController::class, 'updateImage'])->name('catalog.images.update');
        Route::post('katalog/{group}/foto/{image}/pindah', [CatalogController::class, 'moveImage'])->name('catalog.images.move');
        Route::delete('katalog/{group}/foto/{image}', [CatalogController::class, 'destroyImage'])->name('catalog.images.destroy');

        Route::get('artikel', [ArticleController::class, 'index'])->name('articles.index');
        Route::get('artikel/baru', [ArticleController::class, 'create'])->name('articles.create');
        Route::post('artikel', [ArticleController::class, 'store'])->name('articles.store');
        Route::get('artikel/{article}', [ArticleController::class, 'edit'])->name('articles.edit');
        Route::put('artikel/{article}', [ArticleController::class, 'update'])->name('articles.update');
        Route::delete('artikel/{article}', [ArticleController::class, 'destroy'])->name('articles.destroy');

        Route::get('etalase', [EtalaseController::class, 'index'])->name('etalases.index');
        Route::post('etalase', [EtalaseController::class, 'store'])->name('etalases.store');
        Route::put('etalase/{etalase}', [EtalaseController::class, 'update'])->name('etalases.update');
        Route::delete('etalase/{etalase}', [EtalaseController::class, 'destroy'])->name('etalases.destroy');

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

        Route::get('pengaturan', [SettingController::class, 'index'])->name('settings.index');
        Route::put('pengaturan', [SettingController::class, 'update'])->name('settings.update');

        // ---- Master ----
        Route::get('cari-produk', [LookupController::class, 'products'])->name('lookup.products');
        Route::get('variasi', [VariationController::class, 'index'])->name('variations.index');
        Route::get('satuan', [UnitController::class, 'index'])->name('units.index');
        Route::post('satuan', [UnitController::class, 'store'])->name('units.store');
        Route::put('satuan/{unit}', [UnitController::class, 'update'])->name('units.update');
        Route::delete('satuan/{unit}', [UnitController::class, 'destroy'])->name('units.destroy');
        Route::get('konversi-satuan', [ConversionController::class, 'index'])->name('conversions.index');
        Route::post('konversi-satuan', [ConversionController::class, 'store'])->name('conversions.store');
        Route::put('konversi-satuan/{conversion}', [ConversionController::class, 'update'])->name('conversions.update');
        Route::delete('konversi-satuan/{conversion}', [ConversionController::class, 'destroy'])->name('conversions.destroy');
        Route::get('harga', [PriceController::class, 'index'])->name('prices.index');
        Route::put('harga', [PriceController::class, 'update'])->name('prices.update');
        Route::post('harga/level', [PriceController::class, 'storeLevel'])->name('price-levels.store');
        Route::delete('harga/level/{level}', [PriceController::class, 'destroyLevel'])->name('price-levels.destroy');
        Route::get('akun', [AccountController::class, 'index'])->name('accounts.index');
        Route::post('akun', [AccountController::class, 'store'])->name('accounts.store');
        Route::put('akun/{account}', [AccountController::class, 'update'])->name('accounts.update');
        Route::delete('akun/{account}', [AccountController::class, 'destroy'])->name('accounts.destroy');
        Route::get('gudang', [WarehouseController::class, 'index'])->name('warehouses.index');
        Route::post('gudang', [WarehouseController::class, 'store'])->name('warehouses.store');
        Route::put('gudang/{warehouse}', [WarehouseController::class, 'update'])->name('warehouses.update');
        Route::resource('supplier', SupplierController::class)->parameters(['supplier' => 'supplier'])->names('suppliers');
        Route::resource('customer', CustomerController::class)->parameters(['customer' => 'customer'])->names('customers');

        // ---- Pembelian ----
        Route::get('pembelian', [PurchaseController::class, 'index'])->name('purchases.index');
        Route::get('pembelian/baru', [PurchaseController::class, 'create'])->name('purchases.create');
        Route::post('pembelian', [PurchaseController::class, 'store'])->name('purchases.store');
        Route::get('pembelian/{purchase}', [PurchaseController::class, 'show'])->name('purchases.show');
        Route::get('pembelian/{purchase}/ubah', [PurchaseController::class, 'edit'])->name('purchases.edit');
        Route::put('pembelian/{purchase}', [PurchaseController::class, 'update'])->name('purchases.update');
        Route::post('pembelian/{purchase}/batal', [PurchaseController::class, 'cancel'])->name('purchases.cancel');
        Route::get('retur-pembelian', [PurchaseReturnController::class, 'index'])->name('purchase-returns.index');
        Route::get('retur-pembelian/baru', [PurchaseReturnController::class, 'create'])->name('purchase-returns.create');
        Route::post('retur-pembelian', [PurchaseReturnController::class, 'store'])->name('purchase-returns.store');
        Route::get('retur-pembelian/{purchaseReturn}', [PurchaseReturnController::class, 'show'])->name('purchase-returns.show');
        Route::post('retur-pembelian/{purchaseReturn}/batal', [PurchaseReturnController::class, 'cancel'])->name('purchase-returns.cancel');
        Route::get('alih-gudang', [TransferController::class, 'index'])->name('transfers.index');
        Route::get('alih-gudang/baru', [TransferController::class, 'create'])->name('transfers.create');
        Route::post('alih-gudang', [TransferController::class, 'store'])->name('transfers.store');
        Route::get('alih-gudang/{transfer}', [TransferController::class, 'show'])->name('transfers.show');
        Route::post('alih-gudang/{transfer}/terima', [TransferController::class, 'receive'])->name('transfers.receive');
        Route::post('alih-gudang/{transfer}/batal', [TransferController::class, 'cancel'])->name('transfers.cancel');
        Route::get('hutang', [PayableController::class, 'index'])->name('payables.index');
        Route::get('bayar-hutang', [PayableController::class, 'payments'])->name('supplier-payments.index');
        Route::get('bayar-hutang/baru', [PayableController::class, 'createPayment'])->name('supplier-payments.create');
        Route::post('bayar-hutang', [PayableController::class, 'storePayment'])->name('supplier-payments.store');
        Route::post('bayar-hutang/{payment}/batal', [PayableController::class, 'cancelPayment'])->name('supplier-payments.cancel');
        Route::get('piutang-supplier', [PayableController::class, 'receivables'])->name('supplier-receivables.index');
        Route::post('piutang-supplier', [PayableController::class, 'receive'])->name('supplier-receivables.receive');
        Route::get('kartu-hutang', [PayableController::class, 'card'])->name('cards.payable');

        // ---- Penjualan ----
        Route::get('penjualan/kasir', [SaleController::class, 'pos'])->name('sales.pos');
        Route::get('penjualan/baru', [SaleController::class, 'create'])->name('sales.create');
        Route::get('penjualan/batal', [SaleController::class, 'cancelled'])->name('sales.cancelled');
        Route::get('penjualan/item', [SaleController::class, 'items'])->name('sales.items');
        Route::get('penjualan', [SaleController::class, 'index'])->name('sales.index');
        Route::post('penjualan', [SaleController::class, 'store'])->name('sales.store');
        Route::get('retur-penjualan', [SaleReturnController::class, 'index'])->name('sale-returns.index');
        Route::get('retur-penjualan/baru', [SaleReturnController::class, 'create'])->name('sale-returns.create');
        Route::post('retur-penjualan', [SaleReturnController::class, 'store'])->name('sale-returns.store');
        Route::get('retur-penjualan/{saleReturn}', [SaleReturnController::class, 'show'])->name('sale-returns.show');
        Route::get('kembalian-lebih', [OverpaymentController::class, 'index'])->name('overpayments.index');
        Route::post('kembalian-lebih', [OverpaymentController::class, 'store'])->name('overpayments.store');

        // ---- Order online ----
        Route::get('order-online/baru', [OnlineOrderController::class, 'index'])->defaults('stage', 'new')->name('online.new');
        Route::get('order-online/proses', [OnlineOrderController::class, 'index'])->defaults('stage', 'process')->name('online.process');
        Route::get('order-online/dikirim', [OnlineOrderController::class, 'index'])->defaults('stage', 'shipped')->name('online.shipped');
        Route::get('order-online/selesai', [OnlineOrderController::class, 'index'])->defaults('stage', 'done')->name('online.done');
        Route::get('order-online/batal', [OnlineOrderController::class, 'index'])->defaults('stage', 'cancelled')->name('online.cancelled');
        Route::post('order-online/{order}/konfirmasi', [OnlineOrderController::class, 'confirm'])->name('online.confirm');
        Route::post('order-online/{order}/kirim', [OnlineOrderController::class, 'ship'])->name('online.ship');
        Route::post('order-online/{order}/selesai', [OnlineOrderController::class, 'complete'])->name('online.complete');

        // ---- Keuangan ----
        Route::get('dp-customer', [DepositController::class, 'index'])->name('deposits.index');
        Route::get('dp-customer/baru', [DepositController::class, 'create'])->name('deposits.create');
        Route::post('dp-customer', [DepositController::class, 'store'])->name('deposits.store');
        Route::get('dp-customer/{deposit}', [DepositController::class, 'show'])->name('deposits.show');
        Route::post('dp-customer/{deposit}/pakai', [DepositController::class, 'apply'])->name('deposits.apply');
        Route::post('dp-customer/{deposit}/kembalikan', [DepositController::class, 'refund'])->name('deposits.refund');
        Route::get('kartu-piutang', [CardController::class, 'receivable'])->name('cards.receivable');
        Route::get('rekap-pendapatan', [RecapController::class, 'index'])->name('recap.index');
        Route::get('kas-harian', [CashController::class, 'index'])->name('cash.index');
        Route::post('kas-harian', [CashController::class, 'store'])->name('cash.store');
        Route::get('kas-harian/{closing}', [CashController::class, 'show'])->name('cash.show');
        Route::get('mutasi-saldo', [BalanceController::class, 'index'])->name('balances.index');
        Route::get('depresiasi', [AssetController::class, 'index'])->name('assets.index');
        Route::post('depresiasi/aset', [AssetController::class, 'store'])->name('assets.store');
        Route::post('depresiasi/hitung', [AssetController::class, 'depreciate'])->name('assets.depreciate');
        Route::post('depresiasi/aset/{asset}/lepas', [AssetController::class, 'dispose'])->name('assets.dispose');
        Route::get('biaya', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::post('biaya', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::post('biaya/{journal}/batal', [ExpenseController::class, 'cancel'])->name('expenses.cancel');

        // ---- Stok ----
        Route::get('mutasi-stok', [StockReportController::class, 'mutation'])->name('stock.mutation');
        Route::get('kartu-stok', [StockReportController::class, 'card'])->name('stock.card');
        Route::get('penyesuaian-stok', [AdjustmentController::class, 'index'])->name('adjustments.index');
        Route::get('penyesuaian-stok/baru', [AdjustmentController::class, 'create'])->name('adjustments.create');
        Route::post('penyesuaian-stok', [AdjustmentController::class, 'store'])->name('adjustments.store');
        Route::get('penyesuaian-stok/{adjustment}', [AdjustmentController::class, 'show'])->name('adjustments.show');
        Route::get('stok-opname', [OpnameController::class, 'index'])->name('opnames.index');
        Route::get('stok-opname/baru', [OpnameController::class, 'create'])->name('opnames.create');
        Route::post('stok-opname', [OpnameController::class, 'store'])->name('opnames.store');
        Route::get('stok-opname/{opname}', [OpnameController::class, 'show'])->name('opnames.show');
        Route::post('stok-opname/{opname}', [OpnameController::class, 'save'])->name('opnames.save');
        Route::delete('stok-opname/{opname}', [OpnameController::class, 'destroy'])->name('opnames.destroy');

        // ---- Laporan ----
        Route::get('buku-besar', [ReportController::class, 'ledger'])->name('reports.ledger');
        Route::get('laba-rugi', [ReportController::class, 'income'])->name('reports.income');
        Route::get('neraca', [ReportController::class, 'balance'])->name('reports.balance');
        Route::get('neraca-saldo', [ReportController::class, 'trial'])->name('reports.trial');
        Route::get('jurnal-penyesuaian', [AdjustmentJournalController::class, 'index'])->name('adjustment-journals.index');
        Route::get('jurnal-penyesuaian/baru', [AdjustmentJournalController::class, 'create'])->name('adjustment-journals.create');
        Route::post('jurnal-penyesuaian', [AdjustmentJournalController::class, 'store'])->name('adjustment-journals.store');
        Route::post('jurnal-penyesuaian/{journal}/batal', [AdjustmentJournalController::class, 'cancel'])->name('adjustment-journals.cancel');

        Route::get('perangkat', [DeviceController::class, 'index'])->name('devices.index');
        Route::post('perangkat', [DeviceController::class, 'store'])->name('devices.store');
        Route::delete('perangkat/{token}', [DeviceController::class, 'revoke'])->name('devices.revoke');
    });
});
