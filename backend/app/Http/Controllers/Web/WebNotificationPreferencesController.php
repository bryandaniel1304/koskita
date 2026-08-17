<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Satu endpoint kecil dipakai bersama oleh penyewa (web/profile.blade.php)
 * DAN pemilik (web/owner/settings.blade.php) lewat partial yang sama
 * (web/partials/notification-preferences.blade.php) -- persis pola yang
 * sama dengan two-factor-settings.blade.php, cuma controller-nya sengaja
 * dipisah sendiri (bukan ditumpuk ke WebProfileController yang khusus
 * penyewa) karena ini memang lintas-role.
 */
class WebNotificationPreferencesController extends Controller
{
    public function update(Request $request)
    {
        // Checkbox HTML yang tidak dicentang tidak ikut terkirim sama
        // sekali -- makanya pakai boolean() per field (balas false kalau
        // memang tidak ada di request), bukan required|boolean.
        $request->user()->update([
            'notify_bookings' => $request->boolean('notify_bookings'),
            'notify_messages' => $request->boolean('notify_messages'),
            'notify_waitlist' => $request->boolean('notify_waitlist'),
        ]);

        return back()->with('status', 'Preferensi notifikasi tersimpan.');
    }
}
