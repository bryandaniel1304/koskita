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
            // Verifikasi 2 langkah opsional lewat kode OTP email -- SENGAJA
            // tidak bikin tabel terpisah, cukup 3 kolom di sini karena cuma
            // 1 kode aktif per user di satu waktu (kode baru menimpa yang
            // lama, bukan riwayat yang perlu disimpan).
            $table->boolean('two_factor_enabled')->default(false)->after('role');
            $table->string('two_factor_code')->nullable()->after('two_factor_enabled');
            $table->timestamp('two_factor_expires_at')->nullable()->after('two_factor_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_enabled', 'two_factor_code', 'two_factor_expires_at']);
        });
    }
};
