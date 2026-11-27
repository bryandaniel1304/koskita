<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bikin dua tingkat admin dalam satu dashboard yang sama (lihat
 * IsMasterAdmin middleware): admin biasa cuma pegang menu operasional
 * harian (Kos, Fasilitas & Aturan, Booking, Verifikasi, Artikel,
 * Pencarian Nihil), sementara master admin nambah wewenang sensitif --
 * Kelola Pengguna (termasuk naikkan/turunkan role & hapus akun),
 * Pengumuman, dan Laporan. Flag ini cuma berarti kalau role='admin';
 * default FALSE supaya admin lama (mis. hasil seeder) TIDAK otomatis
 * jadi master -- harus dinaikkan manual lewat command/Kelola Pengguna.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_master_admin')->default(false)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_master_admin');
        });
    }
};
