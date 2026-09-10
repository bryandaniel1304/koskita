<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak audit perubahan status booking -- sebelumnya panel admin cuma
 * menampilkan status TERAKHIR, jadi kalau ada sengketa ("kok booking saya
 * ditolak?") tidak ada yang bisa menunjukkan siapa yang mengubah & kapan.
 * Baris di sini ditulis otomatis oleh Booking::booted(), bukan manual di
 * tiap controller, supaya SEMUA jalur perubahan ikut tercatat: panel
 * admin, dashboard pemilik (web & API), dan pembatalan oleh penyewa.
 *
 * Kolom `field` bikin tabel ini dipakai bersama untuk dua "status" yang
 * tampil di detail booking -- status pengajuan & status pembayaran --
 * tanpa perlu tabel kedua yang bentuknya sama persis.
 *
 * actor_name & actor_role disimpan sebagai SALINAN (bukan cuma FK) supaya
 * riwayat tetap terbaca setelah akun pelakunya dihapus -- justru di kasus
 * itulah jejak audit paling dibutuhkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('field')->default('status');
            $table->string('from_value')->nullable();
            $table->string('to_value');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->string('actor_role')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_activity_logs');
    }
};
