<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Versi API dari middleware "verified" bawaan Laravel -- pesannya
 * berbahasa Indonesia supaya konsisten dengan pesan error lain di app,
 * dan selalu balas JSON (bukan redirect ke route web verification.notice
 * yang tidak relevan untuk client mobile).
 */
class EnsureEmailIsVerifiedApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->hasVerifiedEmail()) {
            throw new HttpResponseException(response()->json([
                'message' => 'Verifikasi email kamu dulu sebelum melakukan ini. Cek inbox atau kirim ulang lewat halaman Profil.',
            ], 403));
        }

        return $next($request);
    }
}
