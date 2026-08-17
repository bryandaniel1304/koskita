<?php

namespace App\Models\Source;

use Illuminate\Database\Eloquent\Model;

class SourceBooking extends Model
{
    protected $connection = 'source';
    protected $table = 'bookings';

    protected $casts = [
        'start_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(SourceUser::class, 'user_id');
    }

    public function kos()
    {
        return $this->belongsTo(SourceKos::class, 'kos_id');
    }
}
