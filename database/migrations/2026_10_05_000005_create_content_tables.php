<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ulasan pelanggan. Tidak ada data contoh: hanya ulasan asli yang tampil, setelah disetujui admin.
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('role', 80)->nullable(); // mis. "Pelanggan sejak 2022" / "Guru SD"
            $table->unsignedTinyInteger('rating'); // 1-5
            $table->text('body');
            $table->string('source', 12)->default('customer'); // customer (kirim sendiri) | admin (dicatat admin)
            $table->boolean('is_published')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('url')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('note', 160)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->text('message');
            $table->string('ip', 45)->nullable();
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('testimonials');
    }
};
