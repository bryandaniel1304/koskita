<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Facility;
use App\Models\Kos;
use App\Models\User;
use Illuminate\Http\Request;

class WebHomeController extends Controller
{
    public function index(Request $request)
    {
        // "Unggulan" = rating tertinggi dulu, lalu terbaru -- kos yang belum
        // punya ulasan tetap ikut tampil (bukan cuma yang sudah diulas) biar
        // beranda tidak kosong di awal pemakaian.
        $featured = Kos::with(['facilities', 'images', 'reviews'])
            ->withCount('reviews')
            ->get()
            ->sortByDesc(fn (Kos $kos) => [$kos->average_review_rating ?? 0, $kos->id])
            ->take(6)
            ->values();

        $locations = Kos::select('location')->distinct()->orderBy('location')->pluck('location');
        $facilities = Facility::orderBy('name')->get();
        $totalKos = Kos::count();

        // Statistik kepercayaan -- angka ASLI dari data yang ada, ditampilkan
        // di beranda sebagai bukti sosial (bukan testimoni karangan).
        $trustStats = [
            'total_kos' => $totalKos,
            'total_users' => User::where('role', 'user')->count(),
            'total_bookings_confirmed' => Booking::whereIn('status', ['confirmed', 'completed'])->count(),
            'verified_owners' => User::where('role', 'owner')->where('owner_verification_status', 'approved')->count(),
        ];

        return view('web.home', compact('featured', 'locations', 'facilities', 'totalKos', 'trustStats'));
    }
}
