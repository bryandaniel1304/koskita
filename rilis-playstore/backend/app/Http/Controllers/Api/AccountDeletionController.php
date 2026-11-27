<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Hapus akun oleh pengguna sendiri -- WAJIB untuk aplikasi di Google Play
 * yang mengizinkan pembuatan akun (kebijakan "Account deletion"). Dipakai
 * tombol Profil > Hapus Akun di aplikasi rilis Play Store; halaman web
 * /hapus-akun menjelaskan langkah yang sama untuk diisi di Play Console.
 *
 * Yang ikut terhapus: profil preferensi, riwayat interaksi/rating/favorit,
 * booking, ulasan, pesan, token login & token notifikasi (semuanya lewat
 * foreign key cascade di migrasi), plus -- khusus akun Penyedia Kos --
 * seluruh kos miliknya beserta foto, karena listing tanpa pemilik tidak
 * bisa lagi menerima booking.
 */
class AccountDeletionController extends Controller
{
    public function destroy(Request $request)
    {
        $request->validate([
            'konfirmasi' => 'required|in:HAPUS',
        ], [
            'konfirmasi.required' => 'Ketik HAPUS untuk mengonfirmasi penghapusan akun.',
            'konfirmasi.in' => 'Ketik HAPUS (huruf kapital) untuk mengonfirmasi penghapusan akun.',
        ]);

        $user = $request->user();

        if ($user->role === 'admin') {
            return response()->json([
                'message' => 'Akun admin tidak bisa dihapus dari aplikasi. Hapus lewat panel admin oleh admin lain.',
            ], 403);
        }

        DB::transaction(function () use ($user) {
            if ($user->role === 'owner') {
                $koses = Kos::withoutGlobalScope('hanya_terverifikasi')
                    ->with('images')
                    ->where('owner_id', $user->id)
                    ->get();
                foreach ($koses as $kos) {
                    foreach ($kos->images as $image) {
                        if ($image->path) {
                            Storage::disk('public')->delete($image->path);
                        }
                    }
                    $kos->delete();
                }
            }

            foreach (['avatar_path', 'qris_image_path', 'owner_verification_document'] as $column) {
                if (! empty($user->{$column})) {
                    Storage::disk('public')->delete($user->{$column});
                }
            }

            $user->tokens()->delete();
            $user->delete();
        });

        return response()->json([
            'message' => 'Akun dan seluruh data terkait sudah dihapus permanen.',
        ]);
    }
}
