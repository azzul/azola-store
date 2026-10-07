<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Katalog bertingkat: produk toko (product_groups) -> variasi dengan SKU sendiri (products).
 * products tetap menjadi satuan yang dijual, dihitung stoknya, dan dipakai kasir.
 * Produk lama otomatis dibungkus grup 1:1 supaya tidak ada yang hilang dari toko.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('brand')->nullable();
            $table->string('summary', 240)->nullable();
            $table->text('description')->nullable();
            $table->json('highlights')->nullable();   // daftar poin keunggulan
            $table->json('specs')->nullable();        // [{label, value}] spesifikasi umum
            $table->json('option_names')->nullable(); // ["Ukuran", "Warna"]
            $table->boolean('auto')->default(false);  // grup 1:1 yang mengikuti produknya
            $table->boolean('is_active')->default(true);
            $table->boolean('is_online')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->bigInteger('price_min')->default(0);
            $table->bigInteger('price_max')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'is_online']);
        });

        Schema::create('etalases', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description', 300)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });

        Schema::create('etalase_product_group', function (Blueprint $table) {
            $table->foreignId('etalase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_group_id')->constrained()->cascadeOnDelete();
            $table->primary(['etalase_id', 'product_group_id']);
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete(); // foto khusus satu variasi
            $table->string('path');            // ukuran besar (halaman detail)
            $table->string('card_path')->nullable();  // persegi untuk kartu
            $table->string('thumb_path')->nullable(); // miniatur galeri
            $table->string('alt')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('group_id')->nullable()->after('id')->constrained('product_groups')->nullOnDelete();
            $table->string('variant_name')->nullable()->after('name');
            $table->json('options')->nullable()->after('variant_name'); // {"Ukuran":"M","Warna":"Hitam"}
            $table->unsignedInteger('sort_order')->default(0);
        });

        // Bungkus produk lama dalam grup 1:1.
        foreach (DB::table('products')->orderBy('id')->get() as $p) {
            $id = DB::table('product_groups')->insertGetId([
                'category_id' => $p->category_id, 'name' => $p->name, 'slug' => $p->slug,
                'description' => $p->description, 'auto' => true,
                'is_active' => $p->is_active, 'is_online' => $p->is_online, 'is_featured' => $p->is_featured,
                'meta_title' => $p->meta_title, 'meta_description' => $p->meta_description,
                'price_min' => $p->price, 'price_max' => $p->price,
                'created_at' => $p->created_at, 'updated_at' => $p->updated_at,
            ]);
            DB::table('products')->where('id', $p->id)->update(['group_id' => $id]);

            if ($p->image_path) {
                DB::table('product_images')->insert([
                    'product_group_id' => $id, 'path' => $p->image_path, 'alt' => $p->name,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('group_id');
            $table->dropColumn(['variant_name', 'options', 'sort_order']);
        });
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('etalase_product_group');
        Schema::dropIfExists('etalases');
        Schema::dropIfExists('product_groups');
    }
};
