<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;

/**
 * Jembatan Reverb untuk client mobile (Sanctum Bearer token). Laravel
 * otomatis mendaftarkan `/broadcasting/auth` lewat routes/channels.php,
 * TAPI pakai middleware "web" (sesi cookie) -- tidak bisa dipakai Flutter
 * yang otentikasinya lewat token, bukan cookie. `auth()` di bawah adalah
 * versi paralel persis sama logikanya (Broadcast::auth() tetap membaca
 * definisi channel di routes/channels.php), cuma didaftarkan di grup
 * auth:sanctum supaya token Bearer dikenali.
 */
class BroadcastingController extends Controller
{
    /**
     * Parameter koneksi publik (bukan rahasia -- sama seperti app key
     * publik Pusher/Firebase, aman dibaca siapa pun yang sudah login)
     * supaya Flutter tidak perlu hardcode port/key sendiri. Host SENGAJA
     * tidak disertakan -- app menghitungnya sendiri dari alamat API yang
     * sedang aktif (lihat AppConfig di Flutter), supaya otomatis ikut
     * kalau pengguna ganti alamat server lewat "Pengaturan Server".
     */
    public function config()
    {
        return response()->json([
            'key' => config('broadcasting.connections.reverb.key'),
            'port' => (int) config('broadcasting.connections.reverb.options.port'),
            'scheme' => config('broadcasting.connections.reverb.options.useTLS') ? 'wss' : 'ws',
        ]);
    }

    public function auth(Request $request)
    {
        return Broadcast::auth($request);
    }
}
