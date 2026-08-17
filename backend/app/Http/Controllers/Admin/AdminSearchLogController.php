<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SearchLog;
use Illuminate\Support\Facades\DB;

/**
 * Permintaan yang belum terpenuhi -- pencarian kos yang hasilnya nihil,
 * dicatat lewat SearchLogService setiap kali /kos (web) atau /api/kos
 * (mobile) dipanggil dengan filter tapi tidak ada satu pun kos yang cocok.
 */
class AdminSearchLogController extends Controller
{
    public function index()
    {
        // Ringkasan kata kunci yang paling sering dicari TAPI tidak
        // pernah ketemu -- ini yang paling langsung berguna buat admin/
        // pemilik lihat "permintaan yang belum terpenuhi", bukan cuma
        // daftar mentah baris per baris.
        $topKeywords = SearchLog::select('keyword', DB::raw('count(*) as total'))
            ->whereNotNull('keyword')
            ->where('keyword', '!=', '')
            ->groupBy('keyword')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $topLocations = SearchLog::select('location', DB::raw('count(*) as total'))
            ->whereNotNull('location')
            ->where('location', '!=', '')
            ->groupBy('location')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $recent = SearchLog::with('user:id,name')->latest()->paginate(20);

        return view('admin.search-logs.index', compact('topKeywords', 'topLocations', 'recent'));
    }
}
