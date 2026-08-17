<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Balasan pemilik kos atas ulasan penyewa -- kolom BARU ditambahkan ke
 * tabel yang sudah ada (bukan tabel baru terpisah, karena relasinya 1:1
 * dengan satu ulasan). Nullable & additive, tidak menyentuh data ulasan
 * yang sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kos_reviews', function (Blueprint $table) {
            $table->text('owner_reply')->nullable()->after('photo_path');
            $table->timestamp('owner_replied_at')->nullable()->after('owner_reply');
        });
    }

    public function down(): void
    {
        Schema::table('kos_reviews', function (Blueprint $table) {
            $table->dropColumn(['owner_reply', 'owner_replied_at']);
        });
    }
};
