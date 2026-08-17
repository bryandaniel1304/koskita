<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Kos;
use Illuminate\Http\Request;

/**
 * Alur booking versi situs -- aturan bisnisnya sengaja dibuat persis sama
 * dengan Api\BookingController (validasi, cek hasAvailableRoom(), status
 * awal "pending") supaya perilaku "ajukan lewat situs" dan "ajukan lewat
 * app" konsisten dari sudut pandang pemilik/admin yang memprosesnya.
 */
class WebBookingController extends Controller
{
    public function index(Request $request)
    {
        $bookings = Booking::with('kos')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return view('web.bookings.index', compact('bookings'));
    }

    public function store(Request $request, $kosId)
    {
        $request->validate([
            'start_date' => 'required|date|after_or_equal:today',
            'duration_months' => 'required|integer|min:1|max:24',
            'notes' => 'nullable|string|max:1000',
        ]);

        $kos = Kos::findOrFail($kosId);

        if (!$kos->hasAvailableRoom()) {
            return back()->withErrors([
                'booking' => 'Maaf, semua kamar di kos ini sedang penuh. Coba cari kos lain atau hubungi pemiliknya langsung.',
            ]);
        }

        Booking::create([
            'user_id' => $request->user()->id,
            'kos_id' => $kos->id,
            'start_date' => $request->input('start_date'),
            'duration_months' => $request->integer('duration_months'),
            'notes' => $request->input('notes'),
            'status' => 'pending',
        ]);

        return redirect()->route('web.bookings.index')
            ->with('status', 'Pengajuan booking berhasil dikirim, menunggu konfirmasi pemilik/admin.');
    }

    public function cancel(Request $request, $id)
    {
        $booking = Booking::where('user_id', $request->user()->id)->findOrFail($id);

        if (!in_array($booking->status, ['pending', 'confirmed'])) {
            return back()->withErrors(['booking' => 'Booking dengan status ini tidak bisa dibatalkan.']);
        }

        $booking->update(['status' => 'cancelled']);

        return back()->with('status', 'Booking berhasil dibatalkan.');
    }

    /**
     * Bukti booking siap-cetak (bukan kwitansi pembayaran resmi -- KosKita
     * tidak memproses transaksi apa pun) -- halaman terpisah tanpa layout
     * situs supaya rapi kalau diprint/disimpan sebagai PDF dari browser.
     */
    public function receipt(Request $request, $id)
    {
        $booking = Booking::with('kos.owner')->where('user_id', $request->user()->id)
            ->whereIn('status', ['confirmed', 'completed'])
            ->findOrFail($id);

        return view('web.bookings.receipt', compact('booking'));
    }
}
