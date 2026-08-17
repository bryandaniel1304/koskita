<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Halaman "Pengaturan Akun" khusus PENYEWA di situs web -- sebelum ini
 * penyewa web sama sekali tidak punya halaman pengaturan (beda dari
 * pemilik yang sudah punya web/owner/settings.blade.php), jadi tidak ada
 * tempat buat kelola 2FA. Sengaja minimal (cuma 2FA untuk sekarang) --
 * bukan reimplementasi penuh halaman Profil di app Flutter.
 */
class WebProfileController extends Controller
{
    public function show(Request $request)
    {
        return view('web.profile', ['user' => $request->user()]);
    }
}
