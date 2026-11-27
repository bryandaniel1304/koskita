<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Bikin (atau reset password) akun master admin lewat CLI -- alternatif
 * DatabaseSeeder yang aman dijalankan di production, karena seeder penuh
 * bakal reset seluruh tabel demo. Idempoten: kalau email sudah ada, cuma
 * password & role yang ditimpa, bukan bikin duplikat.
 */
#[Signature('app:create-master-admin
    {--name=Master Admin : Nama akun admin}
    {--email=admin@koskita.com : Email akun admin}
    {--password= : Password akun admin (kalau kosong, di-generate acak & ditampilkan sekali)}')]
#[Description('Buat atau reset akun master admin KosKita (role=admin).')]
class CreateMasterAdmin extends Command
{
    public function handle(): int
    {
        $name = (string) $this->option('name');
        $email = (string) $this->option('email');
        $password = $this->option('password');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email],
            ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email']]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        $generated = false;
        if (!$password) {
            $password = str()->password(16);
            $generated = true;
        }

        $existing = User::where('email', $email)->first();

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'role' => 'admin',
                'is_master_admin' => true,
                'email_verified_at' => now(),
            ]
        );

        $this->info(($existing ? 'Akun admin diperbarui: ' : 'Akun admin dibuat: ') . $user->email);

        if ($generated) {
            $this->warn('Password digenerate otomatis (catat sekarang, tidak ditampilkan lagi): ' . $password);
        }

        return self::SUCCESS;
    }
}
