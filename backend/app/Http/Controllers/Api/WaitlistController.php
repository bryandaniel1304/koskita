<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kos;
use App\Models\Waitlist;
use Illuminate\Http\Request;

/**
 * "Beri Tahu Saya" saat kos yang sedang penuh kembali punya kamar kosong.
 * Notifikasinya sendiri derived (lihat NotificationController), tabel ini
 * cuma nyimpen "siapa nunggu kos apa" + kapan terakhir diberi tahu.
 */
class WaitlistController extends Controller
{
    public function store(Request $request, $id)
    {
        $kos = Kos::findOrFail($id);

        Waitlist::firstOrCreate([
            'user_id' => $request->user()->id,
            'kos_id' => $kos->id,
        ]);

        return response()->json(['message' => 'Kamu akan diberi tahu begitu kamar tersedia lagi.']);
    }

    public function destroy(Request $request, $id)
    {
        Waitlist::where('user_id', $request->user()->id)->where('kos_id', $id)->delete();

        return response()->json(['message' => 'Berhasil keluar dari daftar tunggu.']);
    }
}
