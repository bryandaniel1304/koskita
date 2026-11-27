<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Kos;
use App\Models\UserInteraction;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminDashboardController extends Controller
{


    public function index()
    {
        $totalUsers = User::where('role', 'user')->count();
        $totalKoses = Kos::count();
        $totalInteractions = UserInteraction::count();
        $avgRating = UserInteraction::whereNotNull('rating')->avg('rating') ?? 0;

        $warmStartUsers = User::where('role', 'user')
            ->whereHas('interactions', fn ($q) => $q->whereNotNull('rating'))
            ->count();
        $coldStartUsers = max(0, $totalUsers - $warmStartUsers);

        $bookingStatusCounts = Booking::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $pendingBookings = $bookingStatusCounts->get('pending', 0);

        return view('admin.dashboard', compact(
            'totalUsers',
            'totalKoses',
            'totalInteractions',
            'avgRating',
            'warmStartUsers',
            'coldStartUsers',
            'bookingStatusCounts',
            'pendingBookings'
        ));
    }

    public function interactions()
    {
        $interactions = UserInteraction::with('user', 'kos')->latest()->get();
        return view('admin.interactions.index', compact('interactions'));
    }
}
