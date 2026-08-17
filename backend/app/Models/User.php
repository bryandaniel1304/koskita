<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\TwoFactorCodeNotification;

#[Fillable(['name', 'email', 'phone', 'password', 'role', 'owner_verification_status', 'owner_verification_document', 'owner_verified_at', 'qris_image_path', 'google_id', 'email_verified_at', 'notify_bookings', 'notify_messages', 'notify_waitlist', 'avatar_path'])]
#[Hidden(['password', 'remember_token', 'two_factor_code', 'two_factor_expires_at'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, \Illuminate\Auth\MustVerifyEmail;

    protected $appends = ['qris_url', 'avatar_url'];

    /** URL publik kode QRIS pemilik (null kalau belum unggah) -- dipakai
     *  mobile & web supaya keduanya tidak perlu bangun URL storage sendiri. */
    public function getQrisUrlAttribute(): ?string
    {
        return $this->qris_image_path ? Storage::disk('public')->url($this->qris_image_path) : null;
    }

    /** URL publik foto profil (null kalau belum unggah -- client tampilkan
     *  lingkaran inisial nama sebagai fallback, lihat UserAvatar di mobile
     *  & partial avatar di web). */
    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'owner_verified_at' => 'datetime',
            'two_factor_expires_at' => 'datetime',
            'password' => 'hashed',
            'notify_bookings' => 'boolean',
            'notify_messages' => 'boolean',
            'notify_waitlist' => 'boolean',
        ];
    }

    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }

    public function koses()
    {
        return $this->hasMany(Kos::class, 'owner_id');
    }

    public function interactions()
    {
        return $this->hasMany(UserInteraction::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    /** Token FCM per perangkat -- satu user bisa punya beberapa (login di
     *  beberapa HP), lihat FcmToken migration untuk kenapa token sendiri
     *  yang unik (bukan pasangan user_id+token). */
    public function fcmTokens()
    {
        return $this->hasMany(FcmToken::class);
    }

    /** Badge "Pemilik Terverifikasi" -- dokumen identitas sudah ditinjau & disetujui admin. */
    public function isVerifiedOwner(): bool
    {
        return $this->owner_verification_status === 'approved' && $this->owner_verified_at !== null;
    }

    /** Override supaya pakai notifikasi kustom (link ke rute reset password
     *  situs sendiri, bahasa Indonesia) -- bukan notifikasi bawaan Laravel
     *  yang mengarah ke rute "password.reset" yang tidak kami daftarkan. */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /** Bikin & kirim kode OTP 6 digit baru, berlaku 10 menit -- dipakai
     *  baik saat login (kalau 2FA aktif) maupun saat pertama mengaktifkan
     *  2FA (buktikan email bisa diakses sebelum disandarkan). */
    public function generateAndSendTwoFactorCode(): void
    {
        $code = (string) random_int(100000, 999999);
        $this->forceFill([
            'two_factor_code' => $code,
            'two_factor_expires_at' => now()->addMinutes(10),
        ])->save();

        $this->notify(new TwoFactorCodeNotification($code));
    }

    /** Cocokkan kode OTP -- kode dihapus setelah dipakai (berhasil ATAU
     *  gagal) supaya tidak bisa dicoba berkali-kali/ditebak (harus minta
     *  kode baru tiap percobaan gagal). */
    public function verifyTwoFactorCode(string $code): bool
    {
        $valid = $this->two_factor_code !== null
            && hash_equals($this->two_factor_code, $code)
            && $this->two_factor_expires_at !== null
            && $this->two_factor_expires_at->isFuture();

        $this->forceFill(['two_factor_code' => null, 'two_factor_expires_at' => null])->save();

        return $valid;
    }
}
