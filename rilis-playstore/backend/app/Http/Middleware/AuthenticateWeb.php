<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

/**
 * Versi situs publik dari middleware "auth" bawaan Laravel -- kalau belum
 * login, arahkan ke halaman login SITUS ini (route "web.login"), bukan ke
 * route "login" bawaan yang dipakai panel admin. Guard tetap sama ("web",
 * satu sesi/tabel users untuk admin & pengguna situs), cuma tujuan redirect
 * yang dibedakan supaya pengunjung tidak nyasar ke form login admin.
 */
class AuthenticateWeb extends Middleware
{
    protected function redirectTo(Request $request): ?string
    {
        return $request->expectsJson() ? null : route('web.login');
    }
}
