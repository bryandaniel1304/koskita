<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email + push (FCM, kalau dikonfigurasi) ke PENYEWA begitu status
 * booking-nya berubah jadi confirmed/rejected/completed -- supaya tetap
 * tahu kabarnya walau lagi tidak buka app/situs. Sengaja tidak dipicu
 * untuk status "pending" (belum ada keputusan apa-apa) atau "cancelled"
 * (penyewa sendiri yang batalkan, tidak perlu diberitahu soal aksinya
 * sendiri).
 *
 * Sinkron (bukan ShouldQueue) -- lihat catatan di ResetPasswordNotification
 * soal kenapa: QUEUE_CONNECTION=database tapi tidak ada queue worker yang
 * jalan terus di lingkungan ini.
 */
class BookingStatusChanged extends Notification
{
    public function __construct(public Booking $booking)
    {
    }

    public function via(object $notifiable): array
    {
        if ($notifiable->notify_bookings === false) {
            return [];
        }

        return ['mail', FcmChannel::class];
    }

    public function toFcm(object $notifiable): array
    {
        $kosName = $this->booking->kos->name;

        $body = match ($this->booking->status) {
            'confirmed' => "Booking kamu untuk \"{$kosName}\" sudah dikonfirmasi pemiliknya.",
            'rejected' => "Booking kamu untuk \"{$kosName}\" ditolak pemiliknya.",
            'completed' => "Masa sewa kamu di \"{$kosName}\" sudah selesai.",
            default => "Status booking kamu untuk \"{$kosName}\" berubah.",
        };

        return [
            'title' => 'Update Booking - KosKita',
            'body' => $body,
            'data' => ['type' => 'booking', 'booking_id' => $this->booking->id, 'status' => $this->booking->status],
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $kos = $this->booking->kos;
        $mail = (new MailMessage)->greeting('Halo, ' . $notifiable->name . '!');

        return match ($this->booking->status) {
            'confirmed' => $mail
                ->subject("Booking \"{$kos->name}\" Dikonfirmasi - KosKita")
                ->line("Kabar baik! Pengajuan booking kamu untuk kos \"{$kos->name}\" sudah **dikonfirmasi** oleh pemiliknya.")
                ->line('Mulai sewa: ' . $this->booking->start_date->translatedFormat('d F Y') . ' (' . $this->booking->duration_months . ' bulan)')
                ->action('Lihat Detail Booking', route('web.bookings.index'))
                ->line('Selanjutnya kamu bisa atur pembayaran langsung dengan pemilik kos lewat menu Pesan di KosKita.'),

            'rejected' => $this->rejectedMail($mail, $kos),

            'completed' => $mail
                ->subject("Masa Sewa \"{$kos->name}\" Selesai - KosKita")
                ->line("Masa sewa kamu di kos \"{$kos->name}\" sudah selesai. Terima kasih sudah menggunakan KosKita!")
                ->line('Sempatkan waktu buat kasih ulasan ya, membantu calon penyewa lain menentukan pilihan.')
                ->action('Tulis Ulasan', route('web.kos.show', $kos->id)),

            default => $mail->subject("Update Booking \"{$kos->name}\" - KosKita")
                ->line("Status booking kamu untuk kos \"{$kos->name}\" berubah jadi \"{$this->booking->status}\"."),
        };
    }

    private function rejectedMail(MailMessage $mail, \App\Models\Kos $kos): MailMessage
    {
        $mail->subject("Booking \"{$kos->name}\" Ditolak - KosKita")
            ->line("Sayang sekali, pengajuan booking kamu untuk kos \"{$kos->name}\" **ditolak** oleh pemiliknya.");

        if ($this->booking->admin_note) {
            $mail->line('Catatan dari pemilik: ' . $this->booking->admin_note);
        }

        return $mail->line('Jangan berkecil hati -- masih banyak kos lain yang mungkin lebih cocok buat kamu.')
            ->action('Cari Kos Lain', route('web.kos.index'));
    }
}
