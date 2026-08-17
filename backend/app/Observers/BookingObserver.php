<?php

namespace App\Observers;

use App\Models\Booking;
use App\Services\WaitlistService;

class BookingObserver
{
    /**
     * Booking pindah status DARI "confirmed" ke apa pun lain (completed/
     * cancelled/rejected -- walau rejected seharusnya tidak pernah lewat
     * confirmed dulu) berarti 1 kamar baru saja bebas -- lihat
     * Kos::getOccupiedRoomsAttribute() yang menghitung live dari jumlah
     * booking berstatus "confirmed".
     */
    public function updated(Booking $booking): void
    {
        if ($booking->wasChanged('status') && $booking->getOriginal('status') === 'confirmed') {
            WaitlistService::checkAndNotify($booking->kos);
        }
    }
}
