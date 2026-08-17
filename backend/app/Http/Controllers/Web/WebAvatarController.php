<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Foto profil -- dipakai bersama oleh penyewa (web/profile.blade.php) DAN
 * pemilik (web/owner/settings.blade.php) lewat partial yang sama
 * (web/partials/avatar-settings.blade.php), persis pola yang sama dengan
 * WebNotificationPreferencesController.
 */
class WebAvatarController extends Controller
{
    public function store(Request $request)
    {
        // Pakai `mimes:...` (bukan rule `image`) -- lihat catatan yang
        // sama di Api\AuthController::uploadAvatar.
        $request->validate([
            'avatar' => 'required|mimes:jpeg,jpg,png,webp|max:4096',
        ]);

        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar_path' => $path]);

        return back()->with('status', 'Foto profil berhasil disimpan.');
    }

    public function destroy(Request $request)
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        return back()->with('status', 'Foto profil dihapus.');
    }
}
