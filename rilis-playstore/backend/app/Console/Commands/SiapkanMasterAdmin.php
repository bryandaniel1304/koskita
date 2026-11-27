<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Dipanggil setiap deploy (rilis-playstore/server/deploy.sh). Membuat akun
 * master admin pertama dari KOSKITA_MASTER_ADMIN_EMAIL -- HANYA kalau di
 * database belum ada satu pun admin, jadi aman dijalankan berulang dan
 * tidak pernah menimpa kata sandi admin yang sudah ada. Kata sandi acak
 * dicetak sekali ke log deploy (file di home akun cPanel, tidak publik).
 */
#[Signature('koskita:siapkan-master-admin')]
#[Description('Buat master admin pertama dari .env bila belum ada admin sama sekali.')]
class SiapkanMasterAdmin extends Command
{
    public function handle(): int
    {
        if (User::where('role', 'admin')->exists()) {
            $this->line('Admin sudah ada -- dilewati.');
            return self::SUCCESS;
        }

        $email = config('koskita.master_admin_email');
        if (! $email) {
            $this->warn('Belum ada admin dan KOSKITA_MASTER_ADMIN_EMAIL belum diisi di .env -- dilewati.');
            return self::SUCCESS;
        }

        return $this->call('app:create-master-admin', [
            '--name' => config('koskita.master_admin_nama'),
            '--email' => $email,
        ]);
    }
}
