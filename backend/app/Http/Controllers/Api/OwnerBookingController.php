<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Notifications\BookingStatusChanged;
use Illuminate\Http\Request;

class OwnerBookingController extends Controller
{
    /**
     * Daftar booking yang masuk untuk semua kos milik pemilik yang login.
     */
    public function index(Request $request)
    {
        $bookings = Booking::with(['kos', 'user'])
            ->whereHas('kos', fn ($q) => $q->where('owner_id', $request->user()->id))
            ->latest()
            ->get();

        return response()->json($bookings);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string|in:confirmed,rejected,completed',
            'admin_note' => 'nullable|string|max:1000',
        ]);

        $booking = Booking::with('kos', 'user')
            ->whereHas('kos', fn ($q) => $q->where('owner_id', $request->user()->id))
            ->findOrFail($id);
        $previousStatus = $booking->status;

        // Cegah overbooking: kalau mau dikonfirmasi tapi kamar sudah penuh
        // (dan booking ini sendiri belum confirmed), tolak dulu.
        if ($request->status === 'confirmed' && $booking->status !== 'confirmed' && !$booking->kos->hasAvailableRoom()) {
            return response()->json([
                'message' => 'Tidak bisa dikonfirmasi -- semua kamar di kos ini sudah penuh terisi.',
            ], 422);
        }

        $booking->update([
            'status' => $request->status,
            'admin_note' => $request->admin_note ?? $booking->admin_note,
        ]);

        if ($previousStatus !== $booking->status && $booking->user) {
            try {
                $booking->user->notify(new BookingStatusChanged($booking));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json([
            'message' => 'Status booking berhasil diperbarui.',
            'booking' => $booking->load('kos', 'user'),
        ]);
    }

    /**
     * Tandai booking sudah/belum dibayar -- PENANDA MANUAL oleh pemilik
     * sendiri (mis. sudah terima transfer/tunai langsung dari penyewa),
     * BUKAN hasil verifikasi payment gateway (KosKita tidak memproses
     * transaksi finansial apapun, lihat Syarat & Ketentuan poin 3).
     * Dipisah dari update() di atas karena siklusnya independen dari
     * status booking (confirmed/rejected/dst).
     */
    public function updatePaymentStatus(Request $request, $id)
    {
        $request->validate([
            'payment_status' => 'required|string|in:unpaid,paid',
        ]);

        $booking = Booking::with('kos')
            ->whereHas('kos', fn ($q) => $q->where('owner_id', $request->user()->id))
            ->findOrFail($id);

        $isPaid = $request->payment_status === 'paid';
        $booking->update([
            'payment_status' => $request->payment_status,
            'paid_at' => $isPaid ? now() : null,
        ]);

        return response()->json([
            'message' => $isPaid ? 'Booking ditandai sudah dibayar.' : 'Booking ditandai belum dibayar.',
            'booking' => $booking->load('kos', 'user'),
        ]);
    }
}
