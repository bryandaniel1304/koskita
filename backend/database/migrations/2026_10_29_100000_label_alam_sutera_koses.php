<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Alam Sutera jadi pilihan area tersendiri. Hasil impor riset menandai
 * seluruh kos di wilayah itu sebagai "Serpong" (Alam Sutera termasuk
 * Serpong Utara), sehingga filter area "Alam Sutera" akan kosong tanpa
 * pelabelan ulang ini. Hanya kolom location yang berubah -- koordinat,
 * jarak ke kampus, dan atribut lain yang dipakai content-based filtering
 * tetap sama.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('koses')
            ->where('location', 'Serpong')
            ->where(function ($q) {
                foreach (['name', 'description'] as $column) {
                    $q->orWhere($column, 'like', '%alam sutera%')
                        ->orWhere($column, 'like', '%alsut%');
                }
            })
            ->update(['location' => 'Alam Sutera']);
    }

    public function down(): void
    {
        DB::table('koses')->where('location', 'Alam Sutera')->update(['location' => 'Serpong']);
    }
};
