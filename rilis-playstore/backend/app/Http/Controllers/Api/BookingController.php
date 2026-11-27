<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Kos;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        // 'kos.owner:id,name' WAJIB dimuat -- dipakai tombol "Chat Pemilik"
        // di my_bookings_screen.dart (Kos.fromJson baca kos.owner.id). Tanpa
        // ini, ownerId selalu null dan tombolnya tidak pernah muncul.
        $bookings = Booking::with('kos.owner:id,name')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json($bookings);
    }

    public function store(Request $request)
    {
        $request->validate([
            'kos_id' => 'required|exists:koses,id',
            'start_date' => 'required|date|after_or_equal:today',
            'duration_months' => 'required|integer|min:1|max:24',
            'notes' => 'nullable|string|max:1000',
        ]);

        $kos = Kos::findOrFail($request->kos_id);

        if (!$kos->hasAvailableRoom()) {
            return response()->json([
                'message' => 'Maaf, semua kamar di kos ini sedang penuh. Coba cari kos lain atau hubungi pemiliknya langsung.',
            ], 422);
        }

        $booking = Booking::create([
            'user_id' => $request->user()->id,
            'kos_id' => $kos->id,
            'start_date' => $request->start_date,
            'duration_months' => $request->duration_months,
            'notes' => $request->notes,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Pengajuan booking berhasil dikirim, menunggu konfirmasi admin.',
            'booking' => $booking->load('kos'),
        ], 201);
    }

    public function cancel($id, Request $request)
    {
        $booking = Booking::where('user_id', $request->user()->id)->findOrFail($id);

        if (!in_array($booking->status, ['pending', 'confirmed'])) {
            return response()->json([
                'message' => 'Booking dengan status ini tidak bisa dibatalkan.',
            ], 422);
        }

        $booking->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Booking berhasil dibatalkan.',
            'booking' => $booking->load('kos'),
        ]);
    }
}
