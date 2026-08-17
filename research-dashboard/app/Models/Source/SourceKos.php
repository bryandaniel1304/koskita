<?php

namespace App\Models\Source;

use Illuminate\Database\Eloquent\Model;

class SourceKos extends Model
{
    protected $connection = 'source';
    protected $table = 'koses';

    public function facilities()
    {
        return $this->belongsToMany(SourceFacility::class, 'kos_facility', 'kos_id', 'facility_id');
    }

    public function rules()
    {
        return $this->belongsToMany(SourceRule::class, 'kos_rule', 'kos_id', 'rule_id');
    }

    public function interactions()
    {
        return $this->hasMany(SourceUserInteraction::class, 'kos_id');
    }
}
