<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Backup basis data terjadwal -- lihat catatan Tier 6 di peta jalan
 * kepercayaan: beberapa kali proses pengembangan sesi ini sempat nyaris
 * kehilangan data karena instance MySQL ganda/korup. `mysqlcheck` manual
 * sesudah insiden itu reaktif; command ini proaktif, dijalankan otomatis
 * lewat scheduler (lihat routes/console.php).
 *
 * Disimpan sebagai file .sql polos (bukan di-gzip) supaya tidak perlu
 * ekstensi PHP tambahan -- untuk skala data skripsi ini ukurannya kecil,
 * tidak jadi masalah.
 */
#[Signature('app:backup-database {--keep=14 : Jumlah backup terbaru yang dipertahankan, sisanya dihapus}')]
#[Description('Backup database MySQL ke storage/app/backups dan buang backup lama (retention).')]
class BackupDatabase extends Command
{
    public function handle(): int
    {
        $connection = config('database.default');
        $config = config("database.connections.$connection");

        if ($connection !== 'mysql') {
            $this->error("Backup ini cuma dibuat untuk koneksi mysql, koneksi aktif sekarang: $connection.");
            return self::FAILURE;
        }

        $mysqldump = $this->locateMysqldump();
        if (!$mysqldump) {
            $this->error('mysqldump.exe tidak ditemukan. Cek lokasi instalasi MySQL (mis. XAMPP) dan sesuaikan locateMysqldump().');
            return self::FAILURE;
        }

        $backupDir = storage_path('app/backups');
        File::ensureDirectoryExists($backupDir);

        $filename = 'koskita-' . now()->format('Y-m-d_H-i-s') . '.sql';
        $path = $backupDir . DIRECTORY_SEPARATOR . $filename;

        $command = sprintf(
            '"%s" --host=%s --port=%s --user=%s %s %s > "%s" 2>&1',
            $mysqldump,
            escapeshellarg($config['host']),
            escapeshellarg((string) $config['port']),
            escapeshellarg($config['username']),
            $config['password'] ? '--password=' . escapeshellarg($config['password']) : '',
            escapeshellarg($config['database']),
            $path
        );

        $this->info("Membuat backup ke: $path");
        exec($command, $output, $exitCode);

        if ($exitCode !== 0 || !File::exists($path) || File::size($path) === 0) {
            $this->error('Backup gagal -- cek apakah mysqld sedang jalan & kredensial di .env benar.');
            if (File::exists($path)) {
                File::delete($path);
            }
            return self::FAILURE;
        }

        $this->info('Backup berhasil (' . round(File::size($path) / 1024, 1) . ' KB).');

        $this->pruneOldBackups($backupDir, (int) $this->option('keep'));

        return self::SUCCESS;
    }

    /**
     * Cari mysqldump.exe -- coba PATH sistem dulu, lalu lokasi umum instalasi
     * XAMPP di Windows (lingkungan dev proyek ini) sebagai fallback.
     */
    protected function locateMysqldump(): ?string
    {
        $candidates = [
            'mysqldump', // andalkan PATH kalau memang sudah terdaftar
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            'C:\\wamp64\\bin\\mysql\\mysql8.0\\bin\\mysqldump.exe',
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === 'mysqldump') {
                exec('where mysqldump 2>NUL', $out, $code);
                if ($code === 0 && !empty($out)) {
                    return trim($out[0]);
                }
                continue;
            }
            if (File::exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /** Simpan cuma N backup terbaru -- cegah storage/app/backups membengkak tanpa batas. */
    protected function pruneOldBackups(string $backupDir, int $keep): void
    {
        $files = collect(File::files($backupDir))
            ->filter(fn ($f) => $f->getExtension() === 'sql')
            ->sortByDesc(fn ($f) => $f->getMTime())
            ->values();

        if ($files->count() <= $keep) {
            return;
        }

        foreach ($files->slice($keep) as $old) {
            File::delete($old->getPathname());
        }

        $this->info('Backup lama dibersihkan, menyisakan ' . $keep . ' terbaru.');
    }
}
