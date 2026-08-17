<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Konten "Tips Ngekos" -- artikel pendek yang menyasar kata kunci
     * pencarian nyata (mis. "cara nego harga kos", "checklist pindahan
     * kos") jauh sebelum orang tahu KosKita ada. Murni tambahan baru,
     * tidak menyentuh tabel manapun yang sudah ada.
     */
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('excerpt', 300);
            $table->longText('body');
            $table->string('cover_image_url')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
