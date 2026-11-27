<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KosImage extends Model
{
    protected $fillable = [
        'kos_id',
        'path',
        'is_cover',
        'sort_order',
    ];

    protected $casts = [
        'is_cover' => 'boolean',
    ];

    protected $appends = ['url'];

    public function kos()
    {
        return $this->belongsTo(Kos::class);
    }

    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->path);
    }
}
