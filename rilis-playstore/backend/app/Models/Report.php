<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Laporan pengguna atas kos atau ulasan yang mencurigakan -- polymorphic,
 * masuk antrian moderasi admin (lihat Admin\AdminReportController).
 */
class Report extends Model
{
    protected $fillable = ['reportable_type', 'reportable_id', 'reporter_id', 'reason', 'details', 'status', 'admin_note'];

    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }
}
