<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    protected $fillable = [
        'user_id',
        'gender',
        'occupation',
        'budget_min',
        'budget_max',
        'preferred_facilities',
        'preferred_rules',
        'preferred_location',
        'completed_at',
    ];

    protected $casts = [
        'preferred_facilities' => 'array',
        'preferred_rules' => 'array',
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
