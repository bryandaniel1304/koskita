<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Status pembayaran MANUAL -- KosKita tidak proses transaksi finansial
 * apapun (lihat Syarat & Ketentuan poin 3), jadi ini cuma penanda yang
 * diisi pemilik sendiri ("sudah saya terima transfer/tunai dari
 * penyewa"), bukan hasil verifikasi payment gateway. Kolom tambahan,
 * default 'unpaid' supaya semua booking lama otomatis konsisten
 * (tidak perlu backfill data).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->enum('payment_status', ['unpaid', 'paid'])->default('unpaid')->after('status');
            $table->timestamp('paid_at')->nullable()->after('payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'paid_at']);
        });
    }
};
