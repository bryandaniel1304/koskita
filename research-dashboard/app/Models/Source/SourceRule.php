<?php

namespace App\Models\Source;

use Illuminate\Database\Eloquent\Model;

class SourceRule extends Model
{
    protected $connection = 'source';
    protected $table = 'rules';
}
