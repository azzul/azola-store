<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku')->unique();
            $table->string('barcode')->nullable()->index();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('unit', 20)->default('pcs');

            // Uang disimpan sebagai bilangan bulat rupiah agar tidak ada selisih pembulatan desimal.
            $table->bigInteger('price')->default(0);
            $table->bigInteger('cost')->default(0); // HPP rata-rata tertimbang

            // Cache stok. Sumber kebenaran tetap tabel stock_movements.
            $table->decimal('stock_qty', 15, 3)->default(0);
            $table->decimal('min_stock', 15, 3)->default(0);

            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_online')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->timestamps();

            $table->index(['is_active', 'is_online']);
            $table->index('updated_at');
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id(); // naik terus: dipakai sebagai kursor sinkronisasi stok
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // purchase, sale, sale_return, adjustment, opname
            $table->decimal('qty_change', 15, 3);
            $table->decimal('qty_after', 15, 3);
            $table->bigInteger('unit_cost')->default(0);
            $table->nullableMorphs('reference');
            $table->string('source', 20)->default('admin'); // web, pos_desktop, pos_android, admin
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};
