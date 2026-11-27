<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * MURNI TAMBAHAN & OPSIONAL PER KOS -- kos yang tidak punya baris di
     * sini (mayoritas, termasuk semua 179 data yang sudah ada) tetap
     * berjalan PERSIS seperti sebelumnya, tidak ada perubahan apa pun ke
     * kolom/logic price, total_rooms, occupied_rooms, atau available_rooms
     * di tabel koses -- itu semua TETAP jadi satu-satunya sumber
     * kebenaran soal harga & ketersediaan kamar secara keseluruhan.
     *
     * Tabel ini sengaja hanya untuk TAMPILAN (kos bisa menampilkan
     * breakdown "3 Kamar AC Rp1.8jt, 2 Kamar Standar Rp1.2jt" ke calon
     * penyewa) -- BUKAN dipakai buat alur booking (penyewa tetap
     * mengajukan booking ke kos secara umum, bukan ke tipe kamar
     * spesifik). Ini keputusan sengaja supaya tidak menyentuh logic
     * booking/occupied_rooms yang sudah dipakai di banyak tempat & data
     * sidang skripsi -- lihat catatan di koskita-mobile-parity-roadmap
     * kenapa fitur ini sempat 2x ditunda sebelum akhirnya dikerjakan
     * dengan pendekatan yang lebih aman ini.
     */
    public function up(): void
    {
        Schema::create('kos_room_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kos_id')->constrained('koses')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('price');
            $table->unsignedInteger('total_rooms');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kos_room_types');
    }
};
