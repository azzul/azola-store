<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique(); // dibuat oleh klien (POS/web): kunci idempotensi
            $table->string('number')->nullable()->unique();
            $table->string('channel', 20); // web, pos_desktop, pos_android, admin
            $table->string('status', 20)->default('completed'); // pending, completed, cancelled
            $table->string('payment_method', 20);
            $table->string('payment_status', 20)->default('unpaid'); // unpaid, partial, paid, refunded

            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 40)->nullable();
            $table->string('customer_email')->nullable();
            $table->text('customer_address')->nullable();
            $table->string('delivery_method', 10)->default('pickup'); // pickup, ship
            $table->text('notes')->nullable();

            $table->bigInteger('subtotal')->default(0);
            $table->bigInteger('discount_total')->default(0);
            $table->bigInteger('tax_total')->default(0);
            $table->bigInteger('shipping_fee')->default(0);
            $table->bigInteger('grand_total')->default(0);
            $table->bigInteger('paid_total')->default(0);
            $table->bigInteger('cogs_total')->default(0);

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('ordered_at')->index();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index('channel');
            $table->index('status');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku');
            $table->string('name');
            $table->decimal('qty', 15, 3);
            $table->bigInteger('price');
            $table->bigInteger('discount')->default(0);
            $table->bigInteger('line_total'); // price*qty - discount
            $table->bigInteger('unit_cost')->default(0); // HPP saat terjual
        });

        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('device_type', 20); // desktop, android
            $table->string('token_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
