<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 40)->nullable()->after('email');
            $table->text('address')->nullable()->after('phone');
        });

        Schema::table('orders', function (Blueprint $table) {
            // Pembeli web yang masuk ke akunnya. Beda dengan user_id (kasir/admin yang mencatat).
            $table->foreignId('customer_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'address']);
        });
    }
};
