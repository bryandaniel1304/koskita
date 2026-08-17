<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pengumuman dari admin -- muncul menyatu di feed notifikasi in-app
 * penyewa/pemilik. Lihat komentar migration untuk alasan tabel terpisah.
 */
class AdminBroadcast extends Model
{
    protected $fillable = [
        'title',
        'message',
        'target_role',
        'created_by',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
