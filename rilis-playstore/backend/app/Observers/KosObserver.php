<?php

namespace App\Observers;

use App\Models\Kos;
use App\Services\WaitlistService;

class KosObserver
{
    /** Pemilik/admin menaikkan total_rooms (mis. buka kamar baru) --
     *  cek apakah ini juga membuat kos ini kembali punya kamar kosong. */
    public function updated(Kos $kos): void
    {
        if ($kos->wasChanged('total_rooms') && $kos->total_rooms > $kos->getOriginal('total_rooms')) {
            WaitlistService::checkAndNotify($kos);
        }
    }
}
