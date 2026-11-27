<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pesan langsung antara penyewa & pemilik -- SENGAJA tabel baru
     * berdiri sendiri (bukan bagian dari tabel lain), murni additive,
     * tidak menyentuh data yang sudah ada sama sekali. "Percakapan"
     * tidak punya tabel sendiri; ia diturunkan dari kombinasi
     * (sender_id, receiver_id) saat query -- lebih sederhana daripada
     * kelola tabel conversations terpisah untuk kebutuhan skripsi ini.
     */
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('receiver_id')->constrained('users')->cascadeOnDelete();
            // Konteks kos yang dibicarakan (opsional) -- ditampilkan sebagai
            // info "Tentang: <nama kos>" di UI, TIDAK dipakai untuk
            // memisah percakapan (satu thread per pasangan pengguna, bukan
            // per kos, supaya lebih mirip chat pada umumnya).
            $table->foreignId('kos_id')->nullable()->constrained('koses')->nullOnDelete();
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['receiver_id', 'sender_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
