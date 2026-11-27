<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Breakdown tipe kamar OPSIONAL per kos (mis. "Kamar AC" Rp1.8jt vs
 * "Kamar Standar" Rp1.2jt) -- MURNI TAMPILAN, tidak dipakai di alur
 * booking/ketersediaan (itu tetap dihitung di level Kos seperti biasa,
 * lihat catatan lengkap di migration create_kos_room_types_table).
 */
class KosRoomType extends Model
{
    use HasFactory;

    protected $fillable = ['kos_id', 'name', 'price', 'total_rooms'];

    public function kos()
    {
        return $this->belongsTo(Kos::class);
    }
}
