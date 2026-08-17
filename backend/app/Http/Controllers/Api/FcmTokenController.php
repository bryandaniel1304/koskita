<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FcmToken;
use Illuminate\Http\Request;

/**
 * Registrasi token perangkat untuk push notification (FCM) -- dipanggil
 * Flutter tiap kali token berubah (login, atau token di-refresh sendiri
 * oleh Firebase SDK secara berkala). Lihat FcmService untuk kenapa
 * kolom "token" sendiri yang unik (bukan pasangan user_id+token): HP yang
 * sama dipakai gantian oleh 2 akun harus otomatis pindah kepemilikan,
 * bukan malah numpuk baris basi.
 */
class FcmTokenController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string|max:255',
            'device_type' => 'nullable|string|in:android,ios',
        ]);

        FcmToken::updateOrCreate(
            ['token' => $validated['token']],
            ['user_id' => $request->user()->id, 'device_type' => $validated['device_type'] ?? null]
        );

        return response()->json(['message' => 'Token perangkat tersimpan.']);
    }

    /** Dipanggil saat logout -- HP ini berhenti menerima push untuk akun
     *  yang baru saja keluar (device_type dibiarkan, cuma dilepas dari
     *  akunnya lewat penghapusan barisnya). */
    public function destroy(Request $request)
    {
        $request->validate(['token' => 'required|string']);

        FcmToken::where('user_id', $request->user()->id)
            ->where('token', $request->token)
            ->delete();

        return response()->json(['message' => 'Token perangkat dihapus.']);
    }
}
