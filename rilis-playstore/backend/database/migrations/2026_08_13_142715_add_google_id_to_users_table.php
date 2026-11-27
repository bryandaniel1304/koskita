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
        Schema::table('users', function (Blueprint $table) {
            // Nullable & unique -- akun yang daftar/masuk lewat Google
            // ke-link ke sini; akun email+password biasa tetap null
            // selamanya. Dicocokkan ke akun EXISTING lewat email kalau
            // sudah pernah daftar manual duluan (bukan bikin akun dobel).
            $table->string('google_id')->nullable()->unique()->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('google_id');
        });
    }
};
