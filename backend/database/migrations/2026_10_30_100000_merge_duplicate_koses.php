<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lebur kos yang tercatat lebih dari sekali di katalog hasil impor riset.
 *
 * Penggabungan saat impor (ImportRealKoses::isSameProperty) sengaja ketat,
 * sehingga listing gedung yang sama lolos sebagai kos terpisah bila namanya
 * berbeda antarplatform ("GP Residence" vs "Kost Gpresiden") atau berupa
 * tipe kamar Mamikos dengan akhiran nama berbeda. Akibatnya satu kos muncul
 * dua kali di rekomendasi dengan harga berbeda. Setiap kelompok di bawah
 * sudah dicek manual: alamat jalan + nomornya identik.
 *
 * Nama pertama tiap kelompok menjadi kos utama (sumber dengan data paling
 * lengkap, mis. Rukita + Google Places). Harga = harga terendah ("mulai
 * dari", sama seperti mergeVariant() saat impor), fasilitas & aturan
 * digabung, dan seluruh data pengguna (rating, favorit, booking, ulasan,
 * waitlist, pesan, laporan, foto, tipe kamar) dipindah ke kos utama.
 * Kelompok yang tidak lengkap ditemukan di basis data dilewati.
 */
return new class extends Migration
{
    public const GROUPS = [
        // Jln Permata Indah 1 Blok B2 No.2 (dua tipe kamar Mamikos)
        ['Kost Jln Permata Indah 1 blok B2 No.2 Tipe A Karawaci', 'Kost Jln Permata Indah 1 Blok B2 No.2 Curug Tangerang'],
        // Studento Blok L5 No.1 & L6 No.1
        ['Rukita Botanica Studento BSD', 'Kost Eksklusif dan Lokasi Strategis di BSD - Rukita Botanica'],
        // Jl. Raya Cisauk-Legok No.8
        ['Rukita Urban Intermoda 2 BSD', 'Kost Dekat Kampus Dan Stasiun Cisauk Dengan Balkon Rukita Ur'],
        // Alesha House Red 3/1, Vanya Park
        ['Alesha House 3-1 Vanya Park BSD', 'KOST ALESHA HOUSE RED 3 NO 1 BSD TANGERANG'],
        // Alesha House Purple 8 No.1, Vanya Park
        ['D.E.A Rooms At Alesha House Vanya park BSD', 'SEWA KOST BULANAN DI VANYA PARK ALESHA HOUSE PURPLE 8 NO 1'],
        // Jl. Wates No.32, Pondok Jagung
        ['GP Residence Alam Sutera Serpong', 'Kost Gpresiden Serpong, Alam Sutera'],
        // Ruko Vila Melati Mas Blok A2 No.30
        ['Kos De Noveran', 'Kos De Novaren'],
        // Perumahan Pondok Jagung Blok AC No.36
        ['Rumah Kost AC 36', 'Rumah Kost Khusus Pria'],
        // Jl. Rajawali BD 59, Pondok Jagung
        ['Kost Serpong WTC murah Pondok Jagung', 'Kost Murah belakang WTC serpong', 'Kos Pondok jagung BD 599', 'Kost Murah strategis di BSD / Melati Mas'],
        // Jl. Raya Serpong KM 7 No.88 FG
        ['Kos Baru - The Bed Kost Serpong', 'Kost Baru Seperti Hotel - Indo Kost'],
    ];

    public function up(): void
    {
        foreach (self::GROUPS as $names) {
            $rows = DB::table('koses')->whereIn('name', $names)->get()->keyBy('name');
            if ($rows->count() !== count($names)) {
                continue;
            }

            DB::transaction(fn () => $this->mergeGroup($rows[$names[0]], collect(array_slice($names, 1))->map(fn ($n) => $rows[$n])));
        }
    }

    protected function mergeGroup(object $keep, $duplicates): void
    {
        $dupIds = $duplicates->pluck('id')->all();
        $all = $duplicates->push($keep);

        // Tipe kos: bila sebagian listing menyatakan khusus putra/putri dan
        // sisanya campur, pakai yang khusus -- lebih aman daripada
        // menampilkan kos khusus pria kepada pengguna wanita.
        $specific = $all->pluck('gender_type')->reject(fn ($g) => $g === 'campur')->unique();
        DB::table('koses')->where('id', $keep->id)->update([
            'price' => $all->min('price'),
            'gender_type' => $specific->count() === 1 ? $specific->first() : $keep->gender_type,
            'updated_at' => now(),
        ]);

        foreach (['kos_facility' => 'facility_id', 'kos_rule' => 'rule_id'] as $pivot => $column) {
            $existing = DB::table($pivot)->where('kos_id', $keep->id)->pluck($column)->all();
            $extra = DB::table($pivot)->whereIn('kos_id', $dupIds)->pluck($column)->unique()->diff($existing);
            DB::table($pivot)->insert($extra->map(fn ($id) => ['kos_id' => $keep->id, $column => $id])->values()->all());
            DB::table($pivot)->whereIn('kos_id', $dupIds)->delete();
        }

        // Interaksi: satu baris per pengguna per kos -- gabungkan bila
        // pengguna yang sama pernah berinteraksi dengan beberapa listing.
        foreach (DB::table('user_interactions')->whereIn('kos_id', $dupIds)->get() as $row) {
            $target = DB::table('user_interactions')->where('user_id', $row->user_id)->where('kos_id', $keep->id)->first();
            if (!$target) {
                DB::table('user_interactions')->where('id', $row->id)->update(['kos_id' => $keep->id]);
                continue;
            }
            DB::table('user_interactions')->where('id', $target->id)->update([
                'rating' => $target->rating ?? $row->rating,
                'is_favorite' => $target->is_favorite || $row->is_favorite,
                'click_count' => $target->click_count + $row->click_count,
            ]);
            DB::table('user_interactions')->where('id', $row->id)->delete();
        }

        // Tabel dengan unique(user_id, kos_id): pindahkan kecuali pengguna
        // itu sudah punya baris di kos utama.
        foreach (['kos_reviews', 'waitlists'] as $table) {
            $taken = DB::table($table)->where('kos_id', $keep->id)->pluck('user_id')->all();
            DB::table($table)->whereIn('kos_id', $dupIds)->whereIn('user_id', $taken)->delete();
            DB::table($table)->whereIn('kos_id', $dupIds)->update(['kos_id' => $keep->id]);
        }

        foreach (['bookings', 'messages', 'kos_room_types'] as $table) {
            DB::table($table)->whereIn('kos_id', $dupIds)->update(['kos_id' => $keep->id]);
        }

        $nextOrder = (int) DB::table('kos_images')->where('kos_id', $keep->id)->max('sort_order') + 1;
        foreach (DB::table('kos_images')->whereIn('kos_id', $dupIds)->orderBy('id')->get() as $image) {
            DB::table('kos_images')->where('id', $image->id)->update(['kos_id' => $keep->id, 'is_cover' => false, 'sort_order' => $nextOrder++]);
        }

        DB::table('reports')->where('reportable_type', 'App\\Models\\Kos')->whereIn('reportable_id', $dupIds)
            ->update(['reportable_id' => $keep->id]);

        DB::table('koses')->whereIn('id', $dupIds)->delete();
    }

    public function down(): void
    {
        // Penggabungan data tidak bisa dibalik secara otomatis.
    }
};
