<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    /**
     * Dibuka dari link di email verifikasi (signed URL bawaan Laravel).
     * Hash di-cek manual (bukan lewat EmailVerificationRequest bawaan)
     * supaya tidak perlu pengguna login sesi web dulu -- akun app Flutter
     * ini otentikasinya token Sanctum, bukan sesi browser.
     */
    public function verify(Request $request, $id, $hash)
    {
        $user = User::findOrFail($id);

        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            abort(403, 'Link verifikasi tidak valid.');
        }

        $alreadyVerified = $user->hasVerifiedEmail();

        if (!$alreadyVerified) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return view('email-verified', ['alreadyVerified' => $alreadyVerified]);
    }
}
