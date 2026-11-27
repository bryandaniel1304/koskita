<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot harga & kamar kosong SAAT difavoritkan -- dipakai deteksi
     * "harga turun" / "kamar tersedia lagi" pada kos favorit tanpa perlu
     * tabel riwayat harga terpisah. Diisi ulang tiap kali is_favorite
     * berubah jadi true (lihat KosController::rate).
     */
    public function up(): void
    {
        Schema::table('user_interactions', function (Blueprint $table) {
            $table->integer('favorited_price_snapshot')->nullable()->after('is_favorite');
            $table->integer('favorited_rooms_snapshot')->nullable()->after('favorited_price_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('user_interactions', function (Blueprint $table) {
            $table->dropColumn(['favorited_price_snapshot', 'favorited_rooms_snapshot']);
        });
    }
};
