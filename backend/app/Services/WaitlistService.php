<?php

namespace App\Services;

use App\Models\Kos;
use App\Models\Waitlist;
use App\Notifications\WaitlistSpotAvailable;

/**
 * Beri tahu semua penyewa yang sedang menunggu ("Beri Tahu Saya") begitu
 * sebuah kos kembali punya kamar kosong -- dipanggil dari BookingObserver
 * (booking pindah status DARI "confirmed" ke apa pun = 1 kamar bebas) dan
 * KosObserver (total_rooms pemilik naikkan = kamar baru dibuka), BUKAN
 * dipanggil manual dari tiap controller yang mengubah status booking/kamar
 * -- supaya tidak ada satu pun jalur (Admin/Owner web/Owner API/tenant
 * cancel) yang lupa memicu ini.
 *
 * `notified_at` di tabel waitlists dipakai sebagai penanda "sudah pernah
 * diberi tahu" -- begitu terisi di sini, NotificationController::
 * waitlistAlerts() (yang derived, pull-based, dicek tiap layar Notifikasi
 * dibuka) otomatis berhenti menganggapnya kandidat baru juga, jadi tidak
 * ada notifikasi dobel dari dua jalur berbeda.
 */
class WaitlistService
{
    public static function checkAndNotify(Kos $kos): void
    {
        if (!$kos->hasAvailableRoom()) {
            return;
        }

        $entries = Waitlist::with('user')
            ->where('kos_id', $kos->id)
            ->whereNull('notified_at')
            ->get();

        foreach ($entries as $entry) {
            if (!$entry->user) {
                continue;
            }

            try {
                $entry->user->notify(new WaitlistSpotAvailable($kos));
            } catch (\Throwable $e) {
                report($e);
            }

            $entry->update(['notified_at' => now()]);
        }
    }
}
