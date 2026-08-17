<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Review publik -- TERPISAH dari `user_interactions.rating` (yang
     * privat, cuma dipakai mesin rekomendasi CF). Tabel ini yang tampil ke
     * pengguna lain di halaman detail kos (kepercayaan calon penyewa,
     * standar aplikasi marketplace properti).
     */
    public function up(): void
    {
        Schema::create('kos_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kos_id')->constrained('koses')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating'); // 1-5
            $table->text('comment')->nullable();
            $table->timestamps();

            // Satu user cuma boleh punya satu review aktif per kos (submit
            // ulang = update review lama, bukan bikin duplikat).
            $table->unique(['user_id', 'kos_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kos_reviews');
    }
};
