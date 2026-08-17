<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Artikel "Tips Ngekos" -- konten SEO yang menyasar kata kunci pencarian
 * nyata calon penyewa, bisa diindeks Google (kemampuan yang secara
 * struktural cuma dimiliki web, aplikasi terpasang tidak pernah dirayapi
 * mesin pencari).
 */
class Article extends Model
{
    protected $fillable = ['title', 'slug', 'excerpt', 'body', 'cover_image_url', 'author_id', 'published_at'];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->lte(now());
    }
}
