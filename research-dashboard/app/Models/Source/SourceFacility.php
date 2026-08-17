<?php

namespace App\Models\Source;

use Illuminate\Database\Eloquent\Model;

class SourceFacility extends Model
{
    protected $connection = 'source';
    protected $table = 'facilities';
}
