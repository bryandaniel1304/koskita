<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Beri Tahu Saya" saat kos penuh -- satu baris per (user, kos).
     * notified_at diisi begitu notifikasi "kamar tersedia lagi" ditampilkan,
     * supaya tidak diulang-ulang terus tiap kali user buka layar notifikasi.
     */
    public function up(): void
    {
        Schema::create('waitlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kos_id')->constrained('koses')->cascadeOnDelete();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'kos_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlists');
    }
};
