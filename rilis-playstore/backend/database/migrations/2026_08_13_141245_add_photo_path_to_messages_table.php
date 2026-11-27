<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // Lampiran foto opsional -- pola sama dengan photo_path di
            // kos_reviews (disk "public", path disimpan relatif, URL
            // publik dibangun lewat accessor di model, bukan disimpan
            // sebagai URL utuh).
            //
            // CATATAN: "body" SENGAJA tidak diubah jadi nullable di sini
            // (butuh paket doctrine/dbal buat ->change(), tidak worth
            // nambah dependency cuma buat ini) -- pesan cuma-foto-tanpa-
            // teks tetap didukung dengan menyimpan body sebagai string
            // kosong '', bukan NULL. Lihat MessageController::store.
            $table->string('photo_path')->nullable()->after('body');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
