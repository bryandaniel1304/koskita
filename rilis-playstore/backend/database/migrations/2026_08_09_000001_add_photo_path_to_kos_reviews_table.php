<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ulasan boleh dilampiri satu foto (mis. kondisi kamar sungguhan) --
     * bikin ulasan lebih terpercaya, standar aplikasi marketplace properti.
     */
    public function up(): void
    {
        Schema::table('kos_reviews', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('comment');
        });
    }

    public function down(): void
    {
        Schema::table('kos_reviews', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
