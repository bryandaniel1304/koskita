<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
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

        $booking = Booking::findOrFail($id);
        $booking->update([
            'status' => $request->status,
            'admin_note' => $request->admin_note,
        ]);

        return back()->with('success', 'Status booking berhasil diperbarui.');
    }
}
