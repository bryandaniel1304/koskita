<?php

namespace App\Models\Source;

use Illuminate\Database\Eloquent\Model;

class SourceUserInteraction extends Model
{
    protected $connection = 'source';
    protected $table = 'user_interactions';

    public function user()
    {
        return $this->belongsTo(SourceUser::class, 'user_id');
    }

    public function kos()
    {
        return $this->belongsTo(SourceKos::class, 'kos_id');
    }
}
