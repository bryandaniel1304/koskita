<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Backup database harian -- lihat App\Console\Commands\BackupDatabase.
// Scheduler Laravel butuh SESUATU yang memanggil `php artisan schedule:run`
// tiap menit supaya jadwal ini benar-benar jalan: di server produksi lewat
// cron; di Windows/XAMPP (lingkungan dev proyek ini) lewat Task Scheduler
// yang menjalankan `php artisan schedule:run` tiap menit, ATAU jalankan
// `php artisan app:backup-database` manual/lewat Task Scheduler langsung
// tanpa lapisan schedule:run kalau mau lebih sederhana.
Schedule::command('app:backup-database')->daily()->at('02:00');
