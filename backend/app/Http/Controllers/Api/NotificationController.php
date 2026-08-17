<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminBroadcast;
use App\Models\Booking;
use App\Models\KosReview;
use App\Models\UserInteraction;
use App\Models\Waitlist;
use Illuminate\Http\Request;

/**
 * Feed notifikasi in-app -- SENGAJA "derived" (dihitung langsung dari data
 * booking/ulasan yang sudah ada), bukan tabel notifikasi tersendiri yang
 * perlu ditandai dibaca/belum. Ini pilihan sadar demi kesederhanaan &
 * keandalan (skripsi): tidak ada state tambahan yang bisa tidak-sinkron,
 * cukup di-polling app tiap kali layar Notifikasi dibuka atau lewat
 * pull-to-refresh. Push notification sungguhan (FCM) tetap butuh setup
 * akun Firebase terpisah -- ini pelengkap yang jalan tanpa itu.
 */
class NotificationController extends Controller
{
    protected array $statusLabels = [
        'confirmed' => 'Dikonfirmasi',
        'rejected' => 'Ditolak',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan',
    ];

    public function index(Request $request)
    {
        $user = $request->user();

        $derived = $user->role === 'owner'
            ? $this->ownerNotifications($user)
            : $this->tenantNotifications($user);

        $notifications = collect($derived)
            ->concat($this->broadcastsFor((string) $user->role))
            ->sortByDesc('created_at')
            ->values()
            ->all();

        return response()->json(['notifications' => $notifications]);
    }

    /**
     * Pengumuman admin yang menyasar role user ini (atau ke semua role,
     * target_role null) -- digabung ke feed yang sama biar penyewa/pemilik
     * tidak perlu buka layar terpisah buat lihat pengumuman. $role
     * di-cast string di pemanggil karena role user secara teori bisa null
     * di data lama/rusak -- daripada TypeError 500, anggap saja "bukan
     * user/owner mana pun" (baris whereNull tetap match broadcast global).
     */
    protected function broadcastsFor(string $role): array
    {
        return AdminBroadcast::where(fn ($q) => $q->whereNull('target_role')->orWhere('target_role', $role))
            ->latest()
            ->take(15)
            ->get()
            ->map(fn (AdminBroadcast $b) => [
                'id' => 'broadcast_' . $b->id,
                'type' => 'broadcast',
                'title' => $b->title,
                'message' => $b->message,
                'kos_id' => null,
                'created_at' => $b->created_at,
            ])
            ->all();
    }

    protected function tenantNotifications($user): array
    {
        $bookings = Booking::with('kos:id,name')
            ->where('user_id', $user->id)
            ->whereIn('status', array_keys($this->statusLabels))
            ->latest('updated_at')
            ->take(20)
            ->get();

        $statusChanges = $bookings->map(function (Booking $booking) {
            $kosName = $booking->kos->name ?? 'kos';
            $label = $this->statusLabels[$booking->status] ?? $booking->status;

            return [
                'id' => 'booking_' . $booking->id,
                'type' => 'booking_' . $booking->status,
                'title' => "Booking $label",
                'message' => "Pengajuan booking kamu untuk \"$kosName\" telah $label.",
                'kos_id' => $booking->kos_id,
                'created_at' => $booking->updated_at,
            ];
        });

        $confirmedBookings = Booking::with('kos:id,name')
            ->where('user_id', $user->id)
            ->where('status', 'confirmed')
            ->get();
        $rentReminders = $this->rentReminders($confirmedBookings, tenantView: true);

        $waitlistAlerts = $this->waitlistAlerts($user->id);
        $favoriteAlerts = $this->favoriteChangeAlerts($user->id);
        $reviewPrompts = $this->reviewPrompts($user->id);

        return $statusChanges->concat($rentReminders)->concat($waitlistAlerts)->concat($favoriteAlerts)->concat($reviewPrompts)
            ->sortByDesc('created_at')
            ->values()
            ->all();
    }

    /**
     * "Beri ulasan" begitu masa sewa selesai -- lihat Booking::
     * userHasCompletedStayAt() untuk syarat yang sama persis dipakai buat
     * membolehkan/menolak penulisan ulasan itu sendiri. TIDAK ditandai
     * "sudah dilihat" (beda dari waitlistAlerts) -- sengaja tetap muncul
     * terus selama belum benar-benar ditulis ulasannya, bukan cuma sekali
     * lalu hilang begitu saja padahal ulasannya belum ditulis.
     */
    protected function reviewPrompts(int $userId): array
    {
        $completedBookings = Booking::with('kos:id,name')
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->get();

        $reviewedKosIds = KosReview::where('user_id', $userId)->pluck('kos_id');

        $prompts = [];
        foreach ($completedBookings as $booking) {
            if (!$booking->kos || $reviewedKosIds->contains($booking->kos_id)) {
                continue;
            }
            $prompts[] = [
                'id' => 'review_prompt_' . $booking->id,
                'type' => 'review_prompt',
                'title' => 'Beri Ulasan',
                'message' => "Gimana pengalamanmu tinggal di \"{$booking->kos->name}\"? Yuk beri ulasan buat bantu calon penyewa lain.",
                'kos_id' => $booking->kos_id,
                'created_at' => $booking->updated_at,
            ];
        }

        return $prompts;
    }

    /**
     * Pengingat sewa bulanan -- dihitung dari start_date + kelipatan bulan
     * booking confirmed, TANPA tabel/state tambahan (murni derived). Muncul
     * begitu tanggal jatuh tempo bulanan berikutnya dalam 3 hari ke depan,
     * dan booking belum melewati masa sewa total (duration_months).
     */
    protected function rentReminders($bookings, bool $tenantView): array
    {
        $today = now()->startOfDay();
        $reminders = [];

        foreach ($bookings as $booking) {
            $dueDate = $booking->start_date->copy()->startOfDay();
            $cycle = 0;
            while ($dueDate->lt($today)) {
                $dueDate->addMonth();
                $cycle++;
            }
            if ($cycle >= $booking->duration_months) {
                continue; // masa sewa sudah habis, tidak ada jatuh tempo lagi
            }

            $daysUntilDue = $today->diffInDays($dueDate, false);
            if ($daysUntilDue < 0 || $daysUntilDue > 3) {
                continue;
            }

            $kosName = $booking->kos->name ?? 'kos';
            $when = $daysUntilDue === 0 ? 'hari ini' : "dalam {$daysUntilDue} hari";
            $reminders[] = [
                'id' => 'rent_reminder_' . $booking->id . '_' . $dueDate->format('Ym'),
                'type' => 'rent_reminder',
                'title' => $tenantView ? 'Pengingat Sewa Bulanan' : 'Pengingat Sewa Penyewa',
                'message' => $tenantView
                    ? "Waktunya bayar sewa \"$kosName\" $when ({$dueDate->translatedFormat('d M Y')})."
                    : "Sewa bulanan untuk \"$kosName\" jatuh tempo $when -- boleh diingatkan ke penyewa.",
                'kos_id' => $booking->kos_id,
                'created_at' => $today,
            ];
        }

        return $reminders;
    }

    /**
     * "Kamar tersedia lagi" untuk kos yang di-waitlist -- begitu ditampilkan,
     * notified_at diisi supaya tidak diulang-ulang tiap kali layar dibuka.
     */
    protected function waitlistAlerts(int $userId): array
    {
        $entries = Waitlist::with('kos:id,name,total_rooms')
            ->where('user_id', $userId)
            ->whereNull('notified_at')
            ->get();

        $alerts = [];
        foreach ($entries as $entry) {
            if (!$entry->kos || !$entry->kos->hasAvailableRoom()) {
                continue;
            }
            $alerts[] = [
                'id' => 'waitlist_' . $entry->id,
                'type' => 'waitlist_available',
                'title' => 'Kamar Tersedia Lagi',
                'message' => "Kamar di \"{$entry->kos->name}\" yang kamu tunggu sudah tersedia lagi!",
                'kos_id' => $entry->kos_id,
                'created_at' => now(),
            ];
            $entry->update(['notified_at' => now()]);
        }

        return $alerts;
    }

    /**
     * Perubahan pada kos favorit (harga turun / kamar tersedia lagi)
     * dibanding snapshot saat difavoritkan -- lihat KosController::rate.
     * Tidak ditandai "sudah dilihat" (beda dari waitlist) karena ini lebih
     * mirip status kini yang wajar tetap tampil selama kondisinya masih
     * berlaku, bukan peristiwa sekali kejadian.
     */
    protected function favoriteChangeAlerts(int $userId): array
    {
        $favorites = UserInteraction::with('kos:id,name,price,total_rooms')
            ->where('user_id', $userId)
            ->where('is_favorite', true)
            ->whereNotNull('favorited_price_snapshot')
            ->get();

        $alerts = [];
        foreach ($favorites as $fav) {
            if (!$fav->kos) {
                continue;
            }
            if ($fav->kos->price < $fav->favorited_price_snapshot) {
                $alerts[] = [
                    'id' => 'price_drop_' . $fav->id,
                    'type' => 'price_drop',
                    'title' => 'Harga Turun',
                    'message' => "Harga \"{$fav->kos->name}\" di favoritmu turun jadi Rp " . number_format($fav->kos->price, 0, ',', '.') . '.',
                    'kos_id' => $fav->kos_id,
                    'created_at' => $fav->updated_at,
                ];
            } elseif ($fav->favorited_rooms_snapshot === 0 && $fav->kos->available_rooms > 0) {
                $alerts[] = [
                    'id' => 'rooms_open_' . $fav->id,
                    'type' => 'rooms_available',
                    'title' => 'Kamar Tersedia',
                    'message' => "Kamar di \"{$fav->kos->name}\" (favoritmu) sudah tersedia lagi.",
                    'kos_id' => $fav->kos_id,
                    'created_at' => $fav->updated_at,
                ];
            }
        }

        return $alerts;
    }

    protected function ownerNotifications($user): array
    {
        $pendingBookings = Booking::with('kos:id,name')
            ->whereHas('kos', fn ($q) => $q->where('owner_id', $user->id))
            ->where('status', 'pending')
            ->latest()
            ->take(15)
            ->get()
            ->map(fn (Booking $booking) => [
                'id' => 'booking_' . $booking->id,
                'type' => 'booking_pending',
                'title' => 'Booking Baru Menunggu',
                'message' => 'Ada pengajuan booking baru untuk "' . ($booking->kos->name ?? 'kos') . '" yang perlu direspons.',
                'kos_id' => $booking->kos_id,
                'created_at' => $booking->created_at,
            ]);

        $newReviews = KosReview::with(['kos:id,name', 'user:id,name'])
            ->whereHas('kos', fn ($q) => $q->where('owner_id', $user->id))
            ->latest()
            ->take(15)
            ->get()
            ->map(fn (KosReview $review) => [
                'id' => 'review_' . $review->id,
                'type' => 'new_review',
                'title' => 'Ulasan Baru',
                'message' => ($review->user->name ?? 'Seseorang') . ' memberi ' . $review->rating . '★ untuk "' . ($review->kos->name ?? 'kos') . '".',
                'kos_id' => $review->kos_id,
                'created_at' => $review->created_at,
            ]);

        $confirmedBookings = Booking::with('kos:id,name')
            ->whereHas('kos', fn ($q) => $q->where('owner_id', $user->id))
            ->where('status', 'confirmed')
            ->get();
        $rentReminders = $this->rentReminders($confirmedBookings, tenantView: false);

        return $pendingBookings->concat($newReviews)->concat($rentReminders)
            ->sortByDesc('created_at')
            ->values()
            ->all();
    }
}
