<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Badge "Kos Terverifikasi" -- admin menandai kos yang datanya/fotonya
     * sudah dikonfirmasi sesuai aslinya. Additive murni, default null berarti
     * "belum diverifikasi" untuk semua data lama, tidak ada yang berubah.
     */
    public function up(): void
    {
        Schema::table('koses', function (Blueprint $table) {
            $table->timestamp('verified_at')->nullable()->after('total_rooms');
        });
    }

    public function down(): void
    {
        Schema::table('koses', function (Blueprint $table) {
            $table->dropColumn('verified_at');
        });
    }
};
