<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kos extends Model
{
    protected $table = 'koses';

    protected $fillable = [
        'name',
        'price',
        'gender_type',
        'location',
        'distance_to_campus',
        'description',
        'image_url',
    ];

    protected $appends = ['cover_image'];

    public function facilities()
    {
        return $this->belongsToMany(Facility::class, 'kos_facility');
    }

    public function rules()
    {
        return $this->belongsToMany(Rule::class, 'kos_rule');
    }

    public function interactions()
    {
        return $this->hasMany(UserInteraction::class);
    }

    public function images()
    {
        return $this->hasMany(KosImage::class)->orderBy('sort_order');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function getCoverImageAttribute(): ?string
    {
        $cover = $this->images->firstWhere('is_cover', true) ?? $this->images->first();

        return $cover ? $cover->url : $this->image_url;
    }
}
