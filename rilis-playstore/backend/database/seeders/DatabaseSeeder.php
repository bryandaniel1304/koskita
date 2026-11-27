<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Versi rilis: `php artisan db:seed` HANYA mengisi master data (fasilitas &
 * aturan) -- tidak ada kos, pengguna, atau interaksi contoh. Data contoh
 * skripsi sengaja tidak disertakan di salinan rilis ini.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ProduksiSeeder::class);
    }
}
