<?php

namespace App\Notifications;

use App\Models\Message;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Email + push (FCM, kalau dikonfigurasi) ke PENERIMA pesan begitu ada
 * pesan baru masuk -- baik dari penyewa ke pemilik atau sebaliknya
 * (dua-duanya lewat model Message yang sama). Sinkron (bukan ShouldQueue),
 * lihat catatan di ResetPasswordNotification kenapa.
 */
class NewMessageReceived extends Notification
{
    public function __construct(public Message $message)
    {
    }

    public function via(object $notifiable): array
    {
        // Pengaturan Notifikasi -- default true (nyala) kalau belum pernah
        // diatur, lihat migration add_notification_preferences_to_users_table.
        if ($notifiable->notify_messages === false) {
            return [];
        }

        return ['mail', FcmChannel::class];
    }

    public function toFcm(object $notifiable): array
    {
        return [
            'title' => 'Pesan baru dari ' . $this->message->sender->name,
            'body' => Str::limit($this->message->body ?: '📷 Mengirim foto', 100),
            'data' => ['type' => 'message', 'sender_id' => $this->message->sender_id],
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $sender = $this->message->sender;
        $preview = Str::limit($this->message->body, 140);
        $isOwner = $notifiable->role === 'owner';

        return (new MailMessage)
            ->subject("Pesan Baru dari {$sender->name} - KosKita")
            ->greeting('Halo, ' . $notifiable->name . '!')
            ->line("Kamu dapat pesan baru dari **{$sender->name}** di KosKita:")
            ->line('"' . $preview . '"')
            ->action('Balas Pesan', route('web.messages.thread', $sender->id))
            ->line($isOwner
                ? 'Respons cepat bikin kosmu lebih dipercaya calon penyewa.'
                : 'Jangan lupa balas kalau masih ada yang mau ditanyakan.');
    }
}
