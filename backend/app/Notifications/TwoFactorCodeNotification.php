<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Kode verifikasi 2 langkah -- dikirim tiap kali user dengan 2FA aktif
 * login (kode baru per percobaan), atau sekali saat pertama mengaktifkan
 * 2FA (buat buktikan emailnya benar-benar bisa diakses sebelum
 * disandarkan buat login berikutnya).
 */
class TwoFactorCodeNotification extends Notification
{
    public function __construct(public string $code)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Kode Verifikasi KosKita: ' . $this->code)
            ->greeting('Halo, ' . $notifiable->name . '!')
            ->line('Kode verifikasi 2 langkah kamu:')
            ->line(new \Illuminate\Support\HtmlString(
                '<div style="text-align:center;font-size:32px;font-weight:800;letter-spacing:8px;color:#355DDB;margin:12px 0;">' . $this->code . '</div>'
            ))
            ->line('Kode ini berlaku 10 menit dan cuma bisa dipakai sekali. Kalau kamu tidak sedang coba masuk ke akun KosKita, abaikan email ini -- akunmu tetap aman.');
    }
}
