<?php

namespace App\Notifications\Channels;

use App\Services\FcmService;
use Illuminate\Notifications\Notification;

/**
 * Channel notifikasi kustom -- didaftarkan cukup dengan menyebut nama
 * class-nya langsung di method via() notifikasi (mis.
 * `return ['mail', FcmChannel::class];`), Laravel otomatis resolve lewat
 * container tanpa perlu Notification::extend() di provider mana pun.
 *
 * Setiap Notification yang mau kirim push WAJIB implementasikan toFcm()
 * (mirip toMail()) yang balikin ['title' => ..., 'body' => ..., 'data' => [...]].
 */
class FcmChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toFcm')) {
            return;
        }

        $payload = $notification->toFcm($notifiable);
        if (blank($payload['title'] ?? null) || blank($payload['body'] ?? null)) {
            return;
        }

        FcmService::sendToUser($notifiable, $payload['title'], $payload['body'], $payload['data'] ?? []);
    }
}
