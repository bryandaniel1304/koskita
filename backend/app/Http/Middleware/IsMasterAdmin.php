<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Lapis kedua di ATAS IsAdmin -- gerbang menu sensitif (Kelola Pengguna,
 * Pengumuman, Laporan) yang cuma boleh dipegang master admin, bukan admin
 * operasional biasa. Dipasang setelah IsAdmin di route group, jadi kalau
 * IsAdmin sudah meloloskan (role='admin'), di sini tinggal cek flag
 * is_master_admin -- lihat User::isMasterAdmin().
 */
class IsMasterAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check() || !Auth::user()->isMasterAdmin()) {
            abort(403, 'Menu ini khusus Master Admin.');
        }
        return $next($request);
    }
}
