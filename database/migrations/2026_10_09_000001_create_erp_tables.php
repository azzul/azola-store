<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Modul back-office: master (satuan, harga, supplier, customer, gudang), pembelian, retur,
 * hutang/piutang, alih gudang, penyesuaian & opname stok, kas harian, aset tetap.
 * Semua dokumen punya nomor, tanggal, dan jurnal yang terhubung lewat journals.source_*.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- Master ---------------------------------------------------------
        Schema::table('accounts', function (Blueprint $t) {
            $t->boolean('is_active')->default(true);
            $t->string('note')->nullable();
        });

        Schema::create('units', function (Blueprint $t) {
            $t->id();
            $t->string('name', 20)->unique();
            $t->string('note')->nullable();
            $t->timestamps();
        });

        Schema::create('unit_conversions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->string('unit', 20);                 // satuan lebih besar, mis. "dus"
            $t->decimal('factor', 15, 3);           // 1 dus = 12 satuan dasar
            $t->string('barcode')->nullable();
            $t->bigInteger('price')->nullable();    // kosong = harga dasar x faktor
            $t->timestamps();
            $t->unique(['product_id', 'unit']);
        });

        Schema::create('price_levels', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->boolean('is_default')->default(false); // level bawaan memakai products.price
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('product_prices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->foreignId('price_level_id')->constrained()->cascadeOnDelete();
            $t->bigInteger('price');
            $t->timestamps();
            $t->unique(['product_id', 'price_level_id']);
        });

        Schema::create('warehouses', function (Blueprint $t) {
            $t->id();
            $t->string('code', 20)->unique();
            $t->string('name');
            $t->string('address')->nullable();
            $t->boolean('is_main')->default(false); // gudang yang dijual (web + kasir); stok = products.stock_qty
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('stock_balances', function (Blueprint $t) {
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $t->decimal('qty', 15, 3)->default(0);
            $t->primary(['product_id', 'warehouse_id']);
        });

        Schema::table('stock_movements', function (Blueprint $t) {
            $t->foreignId('warehouse_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
        });

        Schema::create('suppliers', function (Blueprint $t) {
            $t->id();
            $t->string('code', 20)->nullable()->unique();
            $t->string('name');
            $t->string('contact')->nullable();
            $t->string('phone', 40)->nullable();
            $t->string('email')->nullable();
            $t->text('address')->nullable();
            $t->unsignedSmallInteger('term_days')->default(0); // tempo bayar bawaan
            $t->text('note')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // akun web, bila ada
            $t->string('code', 20)->nullable()->unique();
            $t->string('name');
            $t->string('phone', 40)->nullable()->index();
            $t->string('email')->nullable();
            $t->text('address')->nullable();
            $t->foreignId('price_level_id')->nullable()->constrained()->nullOnDelete();
            $t->bigInteger('credit_limit')->default(0); // 0 = tanpa piutang
            $t->text('note')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        // ---- Pesanan: tahapan online, piutang, retur ------------------------
        Schema::table('orders', function (Blueprint $t) {
            $t->foreignId('buyer_id')->nullable()->after('customer_id')->constrained('customers')->nullOnDelete();
            $t->string('fulfillment', 12)->nullable()->index(); // new, process, shipped, done, cancelled (khusus web)
            $t->string('courier', 60)->nullable();
            $t->string('tracking_no', 80)->nullable();
            $t->timestamp('shipped_at')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->date('due_date')->nullable();
            $t->bigInteger('returned_total')->default(0); // nilai retur yang mengurangi tagihan
        });

        Schema::create('order_payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 10)->default('payment'); // payment, refund, deposit
            $t->string('method', 20);
            $t->bigInteger('amount');
            $t->timestamp('paid_at')->index();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('note')->nullable();
            $t->timestamps();
        });

        Schema::create('customer_deposits', function (Blueprint $t) {
            $t->id();
            $t->string('number')->nullable()->unique();
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $t->string('customer_name')->nullable();
            $t->string('kind', 10)->default('dp'); // dp, overpay
            $t->string('method', 20);
            $t->date('date')->index();
            $t->bigInteger('amount');
            $t->bigInteger('used_total')->default(0);
            $t->bigInteger('refunded_total')->default(0);
            $t->string('note')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('sale_returns', function (Blueprint $t) {
            $t->id();
            $t->string('number')->nullable()->unique();
            $t->foreignId('order_id')->constrained();
            $t->date('date')->index();
            $t->string('refund_method', 20)->nullable(); // cash, transfer, deposit (sisa setelah mengurangi piutang)
            $t->bigInteger('subtotal')->default(0);
            $t->bigInteger('tax')->default(0);
            $t->bigInteger('total')->default(0);
            $t->bigInteger('offset_total')->default(0); // dipakai mengurangi piutang
            $t->bigInteger('paid_out')->default(0);     // dikembalikan ke pelanggan
            $t->string('reason')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('sale_return_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sale_return_id')->constrained()->cascadeOnDelete();
            $t->foreignId('order_item_id')->constrained('order_items');
            $t->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $t->decimal('qty', 15, 3);
            $t->bigInteger('line_total');
            $t->bigInteger('unit_cost')->default(0);
            $t->boolean('restock')->default(true);
        });

        // ---- Pembelian ------------------------------------------------------
        Schema::create('purchases', function (Blueprint $t) {
            $t->id();
            $t->string('number')->nullable()->unique();
            $t->string('supplier_invoice')->nullable();
            $t->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $t->date('date')->index();
            $t->date('due_date')->nullable();
            $t->string('payment_method', 10)->default('cash'); // cash, bank, credit
            $t->string('status', 10)->default('posted'); // posted, cancelled
            $t->bigInteger('subtotal')->default(0);
            $t->bigInteger('discount')->default(0);
            $t->bigInteger('grand_total')->default(0);
            $t->bigInteger('paid_total')->default(0);     // dibayar saat input + pelunasan + potongan retur
            $t->bigInteger('returned_total')->default(0); // nilai retur (informasi)
            $t->text('note')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('cancelled_at')->nullable();
            $t->timestamps();
        });

        Schema::create('purchase_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $t->string('unit', 20);
            $t->decimal('factor', 15, 3)->default(1);
            $t->decimal('qty', 15, 3);       // dalam satuan yang diinput
            $t->decimal('base_qty', 15, 3);  // dalam satuan dasar
            $t->bigInteger('price');         // per satuan yang diinput
            $t->bigInteger('line_total');
            $t->bigInteger('net_total');     // setelah potongan faktur dibagi proporsional (dasar HPP)
        });

        Schema::create('purchase_returns', function (Blueprint $t) {
            $t->id();
            $t->string('number')->nullable()->unique();
            $t->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $t->date('date')->index();
            $t->string('settlement', 12)->default('payable'); // payable (potong hutang), cash, bank, receivable (piutang supplier)
            $t->string('status', 10)->default('posted');
            $t->bigInteger('total')->default(0);
            $t->string('reason')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('cancelled_at')->nullable();
            $t->timestamps();
        });

        Schema::create('purchase_return_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_return_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $t->string('unit', 20);
            $t->decimal('factor', 15, 3)->default(1);
            $t->decimal('qty', 15, 3);
            $t->decimal('base_qty', 15, 3);
            $t->bigInteger('price');
            $t->bigInteger('line_total');
        });

        // Pembayaran hutang (direction=pay) dan penerimaan pengembalian piutang supplier (direction=receive).
        Schema::create('supplier_payments', function (Blueprint $t) {
            $t->id();
            $t->string('number')->nullable()->unique();
            $t->foreignId('supplier_id')->constrained();
            $t->string('direction', 8)->default('pay');
            $t->string('method', 10); // cash, bank
            $t->date('date')->index();
            $t->bigInteger('amount');
            $t->string('status', 10)->default('posted');
            $t->string('note')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('cancelled_at')->nullable();
            $t->timestamps();
        });

        // Alokasi pelunasan/potongan ke faktur pembelian (FIFO atau pilihan).
        Schema::create('payable_allocations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $t->morphs('source'); // SupplierPayment atau PurchaseReturn
            $t->bigInteger('amount');
            $t->timestamps();
        });

        // ---- Stok: alih gudang, penyesuaian, opname -------------------------
        Schema::create('stock_transfers', function (Blueprint $t) {
            $t->id();
            $t->string('number')->nullable()->unique();
            $t->foreignId('from_warehouse_id')->constrained('warehouses');
            $t->foreignId('to_warehouse_id')->constrained('warehouses');
            $t->date('date')->index();
            $t->string('status', 10)->default('sent'); // sent, received, cancelled
            $t->string('note')->nullable();
            $t->date('received_on')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('stock_transfer_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained();
            $t->decimal('qty', 15, 3);
            $t->decimal('received_qty', 15, 3)->nullable();
            $t->bigInteger('unit_cost')->default(0);
        });

        Schema::create('stock_adjustments', function (Blueprint $t) {
            $t->id();
            $t->string('number')->nullable()->unique();
            $t->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $t->date('date')->index();
            $t->string('reason', 20); // damaged, lost, expired, shrinkage, found, correction, other
            $t->string('note')->nullable();
            $t->bigInteger('value_total')->default(0); // nilai bersih (+ tambah, - kurang)
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('stock_adjustment_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('stock_adjustment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained();
            $t->decimal('qty_change', 15, 3);
            $t->bigInteger('unit_cost')->default(0);
        });

        Schema::create('stock_opnames', function (Blueprint $t) {
            $t->id();
            $t->string('number')->nullable()->unique();
            $t->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $t->date('date')->index();
            $t->string('status', 10)->default('draft'); // draft, final
            $t->string('note')->nullable();
            $t->bigInteger('value_diff')->default(0);
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('finalized_at')->nullable();
            $t->timestamps();
        });

        Schema::create('stock_opname_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('stock_opname_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained();
            $t->decimal('system_qty', 15, 3);
            $t->decimal('counted_qty', 15, 3)->nullable();
            $t->bigInteger('unit_cost')->default(0);
            $t->string('note')->nullable();
        });

        // ---- Keuangan -------------------------------------------------------
        Schema::create('cash_closings', function (Blueprint $t) {
            $t->id();
            $t->string('number')->nullable()->unique();
            $t->date('date')->unique();
            $t->bigInteger('opening')->default(0);
            $t->bigInteger('cash_in')->default(0);
            $t->bigInteger('cash_out')->default(0);
            $t->bigInteger('expected')->default(0);
            $t->bigInteger('counted')->default(0);
            $t->bigInteger('difference')->default(0);
            $t->json('denominations')->nullable(); // {"100000": 3, "50000": 2, ...}
            $t->string('note')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('fixed_assets', function (Blueprint $t) {
            $t->id();
            $t->string('code', 20)->unique();
            $t->string('name');
            $t->date('acquired_on');
            $t->bigInteger('cost');
            $t->bigInteger('salvage')->default(0);
            $t->unsignedSmallInteger('life_months');
            $t->string('status', 10)->default('active'); // active, disposed
            $t->bigInteger('depreciated')->default(0);
            $t->string('note')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('depreciation_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('fixed_asset_id')->constrained()->cascadeOnDelete();
            $t->char('period', 7); // 2026-10
            $t->bigInteger('amount');
            $t->timestamps();
            $t->unique(['fixed_asset_id', 'period']);
        });

        $this->seedDefaults();
    }

    private function seedDefaults(): void
    {
        $now = now();

        $mainId = DB::table('warehouses')->insertGetId([
            'code' => 'TOKO', 'name' => 'Toko (gudang jual)', 'is_main' => true, 'is_active' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        DB::table('price_levels')->insert([
            ['name' => 'Ecer', 'is_default' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('stock_movements')->whereNull('warehouse_id')->update(['warehouse_id' => $mainId]);

        $units = DB::table('products')->distinct()->pluck('unit')->filter()->push('pcs')->unique();
        foreach ($units as $unit) {
            DB::table('units')->insertOrIgnore(['name' => $unit, 'created_at' => $now, 'updated_at' => $now]);
        }

        // Pesanan lama: isi tahapan online dan riwayat pembayaran dari data yang sudah ada.
        foreach (DB::table('orders')->orderBy('id')->get() as $o) {
            $fulfillment = null;
            if ($o->channel === 'web') {
                $fulfillment = $o->status === 'cancelled' ? 'cancelled'
                    : ($o->status === 'completed' ? 'done' : ($o->paid_total > 0 || $o->payment_method === 'cod' ? 'process' : 'new'));
            }
            DB::table('orders')->where('id', $o->id)->update(['fulfillment' => $fulfillment]);

            if ($o->paid_total > 0) {
                DB::table('order_payments')->insert([
                    'order_id' => $o->id, 'kind' => 'payment', 'method' => $o->payment_method,
                    'amount' => $o->paid_total, 'paid_at' => $o->ordered_at, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        foreach ([
            'depreciation_runs', 'fixed_assets', 'cash_closings', 'stock_opname_items', 'stock_opnames',
            'stock_adjustment_items', 'stock_adjustments', 'stock_transfer_items', 'stock_transfers',
            'payable_allocations', 'supplier_payments', 'purchase_return_items', 'purchase_returns',
            'purchase_items', 'purchases', 'sale_return_items', 'sale_returns', 'customer_deposits', 'order_payments',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::table('orders', function (Blueprint $t) {
            $t->dropConstrainedForeignId('buyer_id');
            $t->dropColumn(['fulfillment', 'courier', 'tracking_no', 'shipped_at', 'delivered_at', 'due_date', 'returned_total']);
        });
        Schema::table('stock_movements', fn (Blueprint $t) => $t->dropConstrainedForeignId('warehouse_id'));

        Schema::table('accounts', fn (Blueprint $t) => $t->dropColumn(['is_active', 'note']));

        foreach (['customers', 'suppliers', 'stock_balances', 'warehouses', 'product_prices', 'price_levels', 'unit_conversions', 'units'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
