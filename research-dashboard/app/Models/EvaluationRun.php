<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluationRun extends Model
{
    protected $fillable = [
        'strategy',
        'alpha',
        'k',
        'users_evaluated',
        'precision',
        'recall',
        'ndcg',
        'map',
        'batch_label',
    ];
}
