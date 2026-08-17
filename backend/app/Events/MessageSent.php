<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dipancarkan lewat Laravel Reverb (self-hosted, tanpa akun pihak ketiga)
 * begitu ada pesan baru -- dikirim ke channel privat milik PENERIMA
 * (App.Models.User.{id}, sudah ada otorisasinya dari bawaan Laravel di
 * routes/channels.php) supaya kalau lawan bicaranya lagi buka thread-nya,
 * pesan baru langsung muncul tanpa perlu refresh/fetch ulang manual.
 *
 * SENGAJA implements ShouldBroadcastNow (bukan ShouldBroadcast) --
 * ShouldBroadcast dikirim lewat antrian (queue), tapi QUEUE_CONNECTION di
 * project ini "database" tanpa worker yang jalan terus. Kalau di-queue,
 * broadcast-nya cuma nyangkut di tabel jobs dan tidak pernah benar-benar
 * terkirim. Disiarkan sinkron di sini supaya pasti langsung terkirim
 * begitu server Reverb (php artisan reverb:start) sedang aktif.
 */
class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public Message $message)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.' . $this->message->receiver_id)];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'sender_id' => $this->message->sender_id,
            'sender_name' => $this->message->sender?->name,
            'body' => $this->message->body,
            'photo_url' => $this->message->photo_url,
            'kos_id' => $this->message->kos_id,
            'created_at' => $this->message->created_at?->toIso8601String(),
        ];
    }
}
