<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchLog extends Model
{
    protected $fillable = ['user_id', 'keyword', 'location', 'gender_type', 'budget_min', 'budget_max', 'facility_ids'];

    protected $casts = [
        'facility_ids' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
