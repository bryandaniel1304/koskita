<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Default TRUE untuk semuanya -- supaya perilaku pengguna yang sudah
     * ada tidak berubah sama sekali (tetap dapat semua notifikasi seperti
     * sebelum fitur ini ada), pengguna baru yang secara sadar mematikan
     * salah satunya lewat Pengaturan Notifikasi.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('notify_bookings')->default(true)->after('role');
            $table->boolean('notify_messages')->default(true)->after('role');
            $table->boolean('notify_waitlist')->default(true)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['notify_bookings', 'notify_messages', 'notify_waitlist']);
        });
    }
};
