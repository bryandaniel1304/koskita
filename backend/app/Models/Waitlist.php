<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * "Beri Tahu Saya" pada kos yang sedang penuh -- lihat migration
 * create_waitlists_table untuk aturan uniknya (satu baris per user+kos).
 */
class Waitlist extends Model
{
    protected $fillable = ['user_id', 'kos_id', 'notified_at'];

    protected $casts = [
        'notified_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function kos()
    {
        return $this->belongsTo(Kos::class);
    }
}
