<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda bahwa penyewa sudah MEMILIH preferensinya sendiri lewat layar
 * Onboarding. Baris profil dibuat dengan nilai default saat registrasi
 * (supaya RecommendationService tidak error), jadi isi kolom preferensi
 * saja tidak bisa membedakan "dipilih pengguna" dari "default". Selama
 * kolom ini null, aplikasi mewajibkan Onboarding sebelum masuk Beranda.
 *
 * Profil yang sudah ada dianggap sudah lengkap supaya pengguna lama tidak
 * tiba-tiba dipaksa mengisi ulang -- kewajiban ini hanya untuk akun baru.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('preferred_location');
        });

        DB::table('user_profiles')->update(['completed_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });
    }
};
