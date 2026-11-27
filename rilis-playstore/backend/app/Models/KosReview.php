<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KosReview extends Model
{
    protected $fillable = [
        'user_id',
        'kos_id',
        'rating',
        'comment',
        'photo_path',
        'owner_reply',
        'owner_replied_at',
    ];

    protected $casts = [
        'owner_replied_at' => 'datetime',
    ];

    protected $appends = ['photo_url'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function kos()
    {
        return $this->belongsTo(Kos::class);
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path ? asset('storage/' . $this->photo_path) : null;
    }
}
