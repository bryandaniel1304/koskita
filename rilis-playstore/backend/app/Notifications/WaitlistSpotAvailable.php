<?php

namespace App\Notifications;

use App\Models\Kos;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email + push (FCM) ke penyewa yang sedang menunggu ("Beri Tahu Saya")
 * begitu kos yang mereka tunggu kembali punya kamar kosong. Dipicu oleh
 * WaitlistService::checkAndNotify() lewat BookingObserver/KosObserver --
 * lihat situ untuk kapan persisnya ini dikirim. Sinkron (bukan
 * ShouldQueue), sama seperti semua notifikasi lain di project ini.
 */
class WaitlistSpotAvailable extends Notification
{
    public function __construct(public Kos $kos)
    {
    }

    public function via(object $notifiable): array
    {
        if ($notifiable->notify_waitlist === false) {
            return [];
        }

        return ['mail', FcmChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Kamar di \"{$this->kos->name}\" Tersedia Lagi - KosKita")
            ->greeting('Halo, ' . $notifiable->name . '!')
            ->line("Kabar baik! Kos \"{$this->kos->name}\" yang kamu tunggu sekarang punya kamar kosong lagi.")
            ->action('Lihat Kos Ini', route('web.kos.show', $this->kos->id))
            ->line('Buruan ajukan booking sebelum penuh lagi.');
    }

    public function toFcm(object $notifiable): array
    {
        return [
            'title' => 'Kamar Tersedia Lagi',
            'body' => "Kos \"{$this->kos->name}\" yang kamu tunggu sekarang punya kamar kosong.",
            'data' => ['type' => 'waitlist', 'kos_id' => $this->kos->id],
        ];
    }
}
