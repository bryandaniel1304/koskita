<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membatasi akses "Portal Pemilik" (halaman kelola kos/booking milik sendiri
 * lewat browser) hanya untuk akun berperan "owner". Sama seperti
 * [EnsureEmailIsVerifiedWeb], dipisah dari middleware "role" versi API (yang
 * selalu balas JSON 403) karena di sini kita perlu redirect + pesan flash.
 */
class EnsureIsOwnerWeb
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || $user->role !== 'owner') {
            return redirect()->route('web.home')->withErrors([
                'owner' => 'Halaman ini khusus untuk akun Penyedia Kos.',
            ]);
        }

        return $next($request);
    }
}
