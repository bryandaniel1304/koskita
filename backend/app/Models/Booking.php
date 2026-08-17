<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = [
        'user_id',
        'kos_id',
        'start_date',
        'duration_months',
        'notes',
        'status',
        'admin_note',
        'payment_status',
        'paid_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'paid_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function kos()
    {
        return $this->belongsTo(Kos::class);
    }

    /**
     * Dipakai buat memutuskan siapa yang BOLEH menulis ulasan baru --
     * sengaja "completed" saja (bukan "confirmed" juga), karena "confirmed"
     * cuma berarti pemilik menyetujui, belum tentu masa sewanya sudah
     * selesai/sungguh-sungguh sudah menginap. Dipakai bersama oleh
     * Api\ReviewController, Web\WebKosController, dan tempat lain yang
     * perlu tahu status ini (mis. tampilkan/sembunyikan tombol ulasan)
     * supaya syaratnya konsisten di satu tempat saja.
     */
    public static function userHasCompletedStayAt(int $userId, int $kosId): bool
    {
        return static::where('user_id', $userId)
            ->where('kos_id', $kosId)
            ->where('status', 'completed')
            ->exists();
    }
}
