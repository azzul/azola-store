<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('topic', 60)->nullable();   // label topik: "Tips", "Info toko", dst.
            $table->string('excerpt', 320)->nullable();
            $table->longText('body');                  // markdown
            $table->string('cover_path')->nullable();
            $table->string('cover_card_path')->nullable();
            $table->string('cover_alt')->nullable();
            $table->string('author', 80)->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->timestamps();

            $table->index(['is_published', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
