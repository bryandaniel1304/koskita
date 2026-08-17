<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Versi situs publik dari middleware "verified" -- alias "verified" bawaan
 * di app ini sudah dipakai EnsureEmailIsVerifiedApi (selalu balas JSON,
 * untuk client Flutter). Untuk situs (sesi browser biasa), kita perlu
 * redirect back() + pesan flash, bukan JSON 403.
 */
class EnsureEmailIsVerifiedWeb
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->hasVerifiedEmail()) {
            return back()->withErrors([
                'verification' => 'Verifikasi email kamu dulu sebelum melakukan ini. Cek inbox (atau folder Spam), atau kirim ulang di bawah.',
            ])->withInput();
        }

        return $next($request);
    }
}
