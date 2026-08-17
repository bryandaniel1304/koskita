<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kos extends Model
{
    use HasFactory;

    protected $table = 'koses';

    protected $fillable = [
        'owner_id',
        'name',
        'price',
        'gender_type',
        'location',
        'latitude',
        'longitude',
        'distance_to_campus',
        'total_rooms',
        'description',
        'image_url',
        'verified_at',
    ];

    protected $appends = ['cover_image', 'average_review_rating', 'reviews_count', 'occupied_rooms', 'available_rooms', 'rating_breakdown'];

    /**
     * Tanpa cast eksplisit, kolom DECIMAL (latitude/longitude) di-serialize
     * Eloquent sebagai STRING JSON (mis. "-6.3104000"), bukan angka --
     * perilaku default Laravel untuk menjaga presisi. App Flutter mem-parse
     * field ini sebagai `num` murni (`json['latitude'] as num?`), jadi kalau
     * dapat string, parsing-nya gagal dengan TypeError (bukan error jaringan
     * biasa) -- inilah yang bikin Beranda gagal terus dengan pesan
     * "kesalahan tak terduga" begitu ada kos yang koordinatnya terisi.
     */
    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'verified_at' => 'datetime',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function reviews()
    {
        return $this->hasMany(KosReview::class)->latest();
    }

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

    /** Breakdown tipe kamar OPSIONAL (mis. AC vs Standar, harga beda) --
     *  MURNI tampilan, lihat catatan lengkap di KosRoomType. */
    public function roomTypes()
    {
        return $this->hasMany(KosRoomType::class);
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

    /**
     * Rata-rata & jumlah review publik. Sengaja cek relationLoaded() dulu
     * supaya kalau controller sudah eager-load('reviews'), accessor ini
     * pakai koleksi yang sudah ada di memori -- bukan query baru per kos
     * (mencegah N+1 saat serialize daftar banyak kos sekaligus).
     */
    public function getAverageReviewRatingAttribute(): ?float
    {
        $reviews = $this->relationLoaded('reviews') ? $this->reviews : $this->reviews()->get();

        return $reviews->isEmpty() ? null : round($reviews->avg('rating'), 1);
    }

    public function getReviewsCountAttribute(): int
    {
        $reviews = $this->relationLoaded('reviews') ? $this->reviews : $this->reviews()->get();

        return $reviews->count();
    }

    /**
     * Distribusi jumlah ulasan per bintang (1-5) -- dipakai tampilan
     * "rating breakdown" di detail kos (mis. "5 bintang: 12 ulasan").
     * Sama seperti accessor rating/jumlah di atas, pakai koleksi yang
     * sudah di-eager-load kalau ada supaya tidak query N+1.
     */
    public function getRatingBreakdownAttribute(): array
    {
        $reviews = $this->relationLoaded('reviews') ? $this->reviews : $this->reviews()->get();
        $breakdown = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];

        foreach ($reviews as $review) {
            $star = (int) round($review->rating);
            if (isset($breakdown[$star])) {
                $breakdown[$star]++;
            }
        }

        return $breakdown;
    }

    /**
     * Jumlah kamar terisi = jumlah booking berstatus "confirmed" untuk kos
     * ini. SENGAJA tidak disimpan sebagai kolom -- selalu dihitung live
     * dari data booking supaya tidak pernah tidak-sinkron (mis. kalau ada
     * booking yang di-cancel/completed lewat jalur manapun, angka ini
     * otomatis ikut berubah tanpa perlu ada kode tambahan yang jaga
     * konsistensi).
     */
    public function getOccupiedRoomsAttribute(): int
    {
        $bookings = $this->relationLoaded('bookings') ? $this->bookings : $this->bookings()->where('status', 'confirmed')->get();

        return $this->relationLoaded('bookings')
            ? $bookings->where('status', 'confirmed')->count()
            : $bookings->count();
    }

    public function getAvailableRoomsAttribute(): int
    {
        return max(0, $this->total_rooms - $this->occupied_rooms);
    }

    /**
     * Dipakai controller booking/konfirmasi untuk cegah overbooking --
     * bukan accessor (tidak perlu ikut ter-serialize ke JSON).
     */
    public function hasAvailableRoom(): bool
    {
        return $this->available_rooms > 0;
    }
}
