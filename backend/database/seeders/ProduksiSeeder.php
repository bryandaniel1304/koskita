<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\Rule;
use Illuminate\Database\Seeder;

/**
 * Data awal untuk instance PRODUKSI (aplikasi Play Store) -- HANYA master
 * fasilitas & aturan yang dibutuhkan form kos pemilik. Sengaja TIDAK berisi
 * kos, pengguna, atau interaksi apa pun (beda dengan DatabaseSeeder yang
 * berisi data contoh untuk skripsi). Aman dijalankan berulang kali.
 *
 *   php artisan db:seed --class=ProduksiSeeder --force
 */
class ProduksiSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['AC', 'WiFi', 'KM Dalam', 'Dapur', 'Parkir', 'Laundry'] as $name) {
            Facility::firstOrCreate(['name' => $name]);
        }

        foreach (['Jam Malam', 'Tamu Boleh Menginap', 'Bawa Hewan', 'Merokok'] as $name) {
            Rule::firstOrCreate(['name' => $name]);
        }
    }
}
