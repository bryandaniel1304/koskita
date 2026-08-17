<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Menggantikan notifikasi ResetPassword bawaan Laravel (Inggris, link ke
 * route password.reset yang tidak kami pakai) -- rute reset password situs
 * ini custom (/reset-password/{token}, nama route web.password.reset),
 * jadi URL-nya dibangun manual di sini, bukan lewat helper bawaan.
 *
 * SENGAJA TIDAK implements ShouldQueue -- QUEUE_CONNECTION di .env project
 * ini "database", dan tidak ada worker (`php artisan queue:work`) yang
 * jalan terus-menerus di lingkungan dev/sidang ini. Kalau di-queue, email
 * cuma akan nyangkut di tabel `jobs` dan tidak pernah benar-benar terkirim.
 * Dikirim sinkron di sini supaya pasti langsung terkirim (atau exception-nya
 * kelihatan), sama seperti pola sendEmailVerificationNotification() yang
 * sudah ada.
 */
class ResetPasswordNotification extends Notification
{
    public function __construct(public string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url(route('web.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject('Atur Ulang Password KosKita')
            ->greeting('Halo, ' . $notifiable->name . '!')
            ->line('Kami menerima permintaan buat atur ulang password akun KosKita kamu.')
            ->action('Atur Ulang Password', $url)
            ->line('Link ini cuma berlaku selama 60 menit. Kalau kamu tidak merasa minta ini, abaikan saja email ini -- password kamu tidak akan berubah.');
    }
}
