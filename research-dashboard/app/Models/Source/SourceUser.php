<?php

namespace App\Models\Source;

use Illuminate\Database\Eloquent\Model;

/**
 * Baca-saja ke tabel `users` milik database "koskita" (backend admin panel &
 * app Flutter). Model ini TIDAK PERNAH dipakai untuk menulis/migrate --
 * lihat config/database.php koneksi "source".
 */
class SourceUser extends Model
{
    protected $connection = 'source';
    protected $table = 'users';

    public function profile()
    {
        return $this->hasOne(SourceUserProfile::class, 'user_id');
    }

    public function interactions()
    {
        return $this->hasMany(SourceUserInteraction::class, 'user_id');
    }

    public function bookings()
    {
        return $this->hasMany(SourceBooking::class, 'user_id');
    }
}
