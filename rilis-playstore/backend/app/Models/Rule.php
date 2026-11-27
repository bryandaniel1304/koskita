<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rule extends Model
{
    protected $fillable = ['name'];

    public function koses()
    {
        return $this->belongsToMany(Kos::class, 'kos_rule');
    }
}
