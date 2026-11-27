<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Kos;
use App\Models\KosReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Review PUBLIK -- tampil ke pengguna lain di halaman detail kos (standar
 * aplikasi marketplace properti, mis. Airbnb/Mamikos). Terpisah dari rating
 * privat di UserInteraction yang cuma dipakai mesin rekomendasi CF.
 */
class ReviewController extends Controller
{
    public function store(Request $request, $kosId)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
            'photo' => 'nullable|image|max:4096',
        ]);

        $kos = Kos::findOrFail($kosId);
        $user = $request->user();

        $existing = KosReview::where('user_id', $user->id)->where('kos_id', $kos->id)->first();

        // Ulasan BARU (bukan edit ulasan lama) hanya dari penyewa yang masa
        // sewanya untuk kos ini SUDAH SELESAI ("completed") -- bukan cuma
        // "confirmed" (baru disetujui, belum tentu benar-benar sudah
        // menginap/selesai tinggal). Ini sengaja lebih ketat dari sebelumnya
        // supaya ulasan cuma dari yang sungguh-sungguh pernah menyewa &
        // menginap, bukan baru disetujui bookingnya. Ulasan yang SUDAH ADA
        // tetap boleh diedit tanpa syarat ini (jaga kompatibilitas data lama
        // yang dibuat sebelum aturan ini berlaku).
        if (!$existing && !Booking::userHasCompletedStayAt($user->id, $kos->id)) {
            return response()->json([
                'message' => 'Kamu hanya bisa memberi ulasan setelah masa sewamu di kos ini selesai.',
            ], 422);
        }

        $data = ['rating' => $request->rating, 'comment' => $request->comment];

        if ($request->hasFile('photo')) {
            // Ulasan lama diperbarui (bukan bikin baru) -- hapus foto lama
            // dulu kalau ada, supaya tidak ada file yatim menumpuk di disk.
            if ($existing && $existing->photo_path) {
                Storage::disk('public')->delete($existing->photo_path);
            }
            $data['photo_path'] = $request->file('photo')->store('review-photos', 'public');
        }

        $review = KosReview::updateOrCreate(
            ['user_id' => $user->id, 'kos_id' => $kos->id],
            $data
        );

        return response()->json([
            'message' => 'Ulasan berhasil disimpan. Terima kasih!',
            'review' => $review->load('user:id,name'),
        ], 201);
    }

    public function destroy(Request $request, $kosId)
    {
        $review = KosReview::where('user_id', $request->user()->id)
            ->where('kos_id', $kosId)
            ->first();

        if (!$review) {
            return response()->json(['message' => 'Ulasan tidak ditemukan.'], 404);
        }

        if ($review->photo_path) {
            Storage::disk('public')->delete($review->photo_path);
        }
        $review->delete();

        return response()->json(['message' => 'Ulasan berhasil dihapus.']);
    }
}
