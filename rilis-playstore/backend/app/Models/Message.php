<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Pesan langsung antara satu penyewa & satu pemilik. "Percakapan" tidak
 * punya model/tabel sendiri -- diturunkan dari kombinasi sender_id/
 * receiver_id lewat query di [App\Http\Controllers\Api\MessageController].
 */
class Message extends Model
{
    use HasFactory;

    protected $fillable = ['sender_id', 'receiver_id', 'kos_id', 'body', 'photo_path', 'read_at'];
    protected $appends = ['photo_url'];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    /** URL publik foto lampiran (null kalau pesan ini tidak ada fotonya). */
    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function kos()
    {
        return $this->belongsTo(Kos::class);
    }
}
