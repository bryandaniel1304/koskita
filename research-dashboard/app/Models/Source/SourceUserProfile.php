<?php

namespace App\Models\Source;

use Illuminate\Database\Eloquent\Model;

class SourceUserProfile extends Model
{
    protected $connection = 'source';
    protected $table = 'user_profiles';

    protected $casts = [
        'preferred_facilities' => 'array',
        'preferred_rules' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(SourceUser::class, 'user_id');
    }
}
