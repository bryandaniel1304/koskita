<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Notifications\BookingStatusChanged;
use Illuminate\Http\Request;

class AdminBookingController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');

        $bookings = Booking::with('user', 'kos')
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.bookings.index', compact('bookings', 'status'));
    }

    /**
     * Export seluruh booking ke CSV -- laporan buat pemilik/skripsi.
     */
    public function exportCsv()
    {
        $bookings = Booking::with('user', 'kos')->latest()->get();

        $filename = 'booking-koskita-' . now()->format('Y-m-d_H-i-s') . '.csv';

        return response()->streamDownload(function () use ($bookings) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Penyewa', 'Email Penyewa', 'Kos', 'Mulai Sewa', 'Durasi (bulan)', 'Status', 'Pembayaran', 'Catatan Admin', 'Diajukan']);
            foreach ($bookings as $booking) {
                fputcsv($out, [
                    $booking->id,
                    $booking->user?->name ?? '-',
                    $booking->user?->email ?? '-',
                    $booking->kos?->name ?? '-',
                    $booking->start_date?->format('Y-m-d'),
                    $booking->duration_months,
                    $booking->status,
                    $booking->payment_status,
                    $booking->admin_note,
                    $booking->created_at?->format('Y-m-d H:i'),
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function show($id)
    {
        $booking = Booking::with('user.profile', 'kos')->findOrFail($id);

        return view('admin.bookings.show', compact('booking'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,confirmed,rejected,cancelled,completed',
            'admin_note' => 'nullable|string|max:1000',
        ]);

        $booking = Booking::with('kos', 'user')->findOrFail($id);
        $previousStatus = $booking->status;

        if ($request->status === 'confirmed' && $booking->status !== 'confirmed' && !$booking->kos->hasAvailableRoom()) {
            return back()->withErrors(['status' => 'Tidak bisa dikonfirmasi -- semua kamar di kos ini sudah penuh terisi.']);
        }

        $booking->update([
            'status' => $request->status,
            'admin_note' => $request->admin_note,
        ]);

        // Email ke penyewa cuma buat status yang benar-benar sebuah
        // "keputusan" (confirmed/rejected/completed) DAN memang baru
        // berubah -- bukan tiap kali admin klik Simpan dengan status yang
        // sama. Gagal kirim tidak boleh gagalkan aksi update-nya sendiri.
        if ($previousStatus !== $booking->status && in_array($booking->status, ['confirmed', 'rejected', 'completed'], true) && $booking->user) {
            try {
                $booking->user->notify(new BookingStatusChanged($booking));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return back()->with('success', 'Status booking berhasil diperbarui.');
    }
}
