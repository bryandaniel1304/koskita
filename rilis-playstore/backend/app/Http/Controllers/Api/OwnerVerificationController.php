<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Badge "Pemilik Terverifikasi" (dokumen identitas ditinjau admin, lihat
 * Admin\AdminUserController::verifyOwner) + kode QRIS statis yang
 * ditampilkan ke penyewa begitu booking dikonfirmasi. KosKita tetap TIDAK
 * memproses pembayaran apa pun -- ini murni tempat pemilik menaruh info
 * pembayarannya sendiri, transfer tetap manual seperti biasa.
 */
class OwnerVerificationController extends Controller
{
    public function submit(Request $request)
    {
        $request->validate([
            'document' => 'required|image|max:4096',
        ]);

        $user = $request->user();

        if ($user->owner_verification_document) {
            Storage::disk('public')->delete($user->owner_verification_document);
        }

        $path = $request->file('document')->store('owner-verifications', 'public');
        $user->update([
            'owner_verification_document' => $path,
            'owner_verification_status' => 'pending',
            'owner_verified_at' => null,
        ]);

        return response()->json([
            'message' => 'Dokumen berhasil dikirim, menunggu peninjauan admin.',
        ]);
    }

    public function uploadQris(Request $request)
    {
        $request->validate([
            'qris' => 'required|image|max:4096',
        ]);

        $user = $request->user();

        if ($user->qris_image_path) {
            Storage::disk('public')->delete($user->qris_image_path);
        }

        $path = $request->file('qris')->store('owner-qris', 'public');
        $user->update(['qris_image_path' => $path]);

        return response()->json([
            'message' => 'Kode QRIS berhasil disimpan.',
            'qris_url' => Storage::disk('public')->url($path),
        ]);
    }

    public function deleteQris(Request $request)
    {
        $user = $request->user();

        if ($user->qris_image_path) {
            Storage::disk('public')->delete($user->qris_image_path);
            $user->update(['qris_image_path' => null]);
        }

        return response()->json(['message' => 'Kode QRIS dihapus.']);
    }
}
