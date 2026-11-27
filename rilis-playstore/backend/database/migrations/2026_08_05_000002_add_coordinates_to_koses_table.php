<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Koordinat presisi (opsional) untuk pin peta di detail kos. Kalau
     * kosong, frontend fallback ke titik tengah area (Karawaci/BSD/Serpong)
     * berdasarkan kolom `location` yang sudah ada -- jadi peta tetap
     * berfungsi untuk 15 data kos lama yang belum diisi koordinatnya.
     */
    public function up(): void
    {
        Schema::table('koses', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('location');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('koses', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
