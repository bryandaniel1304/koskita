<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('koses', function (Blueprint $table) {
            // Jumlah kamar TOTAL yang dimiliki kos ini. "Terisi"/"tersedia"
            // TIDAK disimpan sebagai kolom terpisah -- selalu dihitung
            // langsung dari jumlah booking berstatus "confirmed" (lihat
            // Kos::getOccupiedRoomsAttribute()), supaya angkanya tidak
            // pernah drift/ tidak sinkron dengan data booking asli.
            $table->unsignedTinyInteger('total_rooms')->default(1)->after('distance_to_campus');
        });
    }

    public function down(): void
    {
        Schema::table('koses', function (Blueprint $table) {
            $table->dropColumn('total_rooms');
        });
    }
};
