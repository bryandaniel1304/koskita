<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kos;
use App\Models\KosReview;
use App\Models\Report;
use Illuminate\Http\Request;

/**
 * Laporan pengguna atas kos/ulasan mencurigakan -- masuk antrian moderasi
 * admin (Admin\AdminReportController). Sengaja terima "type" sebagai string
 * pendek ('kos'/'review') dari client, dipetakan ke FQCN model di sini --
 * client tidak perlu tahu/kirim namespace kelas PHP.
 */
class ReportController extends Controller
{
    protected const TYPE_MAP = [
        'kos' => Kos::class,
        'review' => KosReview::class,
    ];

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string|in:kos,review',
            'id' => 'required|integer',
            'reason' => 'required|string|max:255',
            'details' => 'nullable|string|max:1000',
        ]);

        $modelClass = self::TYPE_MAP[$validated['type']];
        $target = $modelClass::find($validated['id']);
        if (!$target) {
            return response()->json(['message' => 'Data yang dilaporkan tidak ditemukan.'], 404);
        }

        $report = Report::create([
            'reportable_type' => $modelClass,
            'reportable_id' => $target->id,
            'reporter_id' => $request->user()->id,
            'reason' => $validated['reason'],
            'details' => $validated['details'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Laporan terkirim, terima kasih. Tim kami akan meninjaunya.',
            'report' => $report,
        ], 201);
    }
}
