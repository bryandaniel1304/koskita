<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuma nyimpen pencarian yang BENAR-BENAR pakai filter dan hasilnya
     * kosong -- lihat SearchLogService::logIfEmpty(). Bukan log semua
     * pencarian (itu akan tumbuh besar tanpa guna jelas), tujuannya murni
     * membantu admin/pemilik lihat permintaan yang belum terpenuhi
     * (mis. "banyak yang cari kos budget 500rb di lokasi X, tidak ada").
     */
    public function up(): void
    {
        Schema::create('search_logs', function (Blueprint $table) {
            $table->id();
            // Nullable -- pencarian tamu (belum login) tetap berharga buat
            // dicatat, bukan cuma dari pengguna yang sudah punya akun.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('keyword')->nullable();
            $table->string('location')->nullable();
            $table->string('gender_type')->nullable();
            $table->unsignedInteger('budget_min')->nullable();
            $table->unsignedInteger('budget_max')->nullable();
            $table->json('facility_ids')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_logs');
    }
};
