<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('key', 40)->nullable()->unique(); // kunci tetap untuk akun sistem (cash, sales, ...)
            $table->string('name');
            $table->string('type', 20); // asset, liability, equity, revenue, cogs, expense
            $table->string('normal_balance', 6); // debit | credit
            $table->timestamps();
        });

        Schema::create('journals', function (Blueprint $table) {
            $table->id();
            $table->string('number')->nullable()->unique();
            $table->date('date')->index();
            $table->string('type', 20); // sale, payment, purchase, adjustment, opening, reversal
            $table->string('description');
            $table->nullableMorphs('source');
            $table->foreignId('reversal_of_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->foreignId('reversed_by_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->bigInteger('total');
            $table->timestamps();
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained();
            $table->bigInteger('debit')->default(0);
            $table->bigInteger('credit')->default(0);
            $table->string('memo')->nullable();

            $table->index('account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journals');
        Schema::dropIfExists('accounts');
    }
};
