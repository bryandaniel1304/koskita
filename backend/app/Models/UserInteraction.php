<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserInteraction extends Model
{
    protected $fillable = [
        'user_id',
        'kos_id',
        'rating',
        'is_favorite',
        'click_count',
        'favorited_price_snapshot',
        'favorited_rooms_snapshot',
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
