<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu jawaban SUS dari responden panel Populix (lihat migrasi
 * create_populix_sus_responses_table). Pernyataan dan rumus skornya sama
 * dengan SusResponse; bedanya hanya sumber responden.
 */
class PopulixSusResponse extends Model
{
    protected $fillable = [
        'populix_id',
        'gender',
        'age',
        'province',
        'city',
        'completed_at',
        'q1', 'q2', 'q3', 'q4', 'q5', 'q6', 'q7', 'q8', 'q9', 'q10',
        'sus_score',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'sus_score' => 'float',
    ];
}
