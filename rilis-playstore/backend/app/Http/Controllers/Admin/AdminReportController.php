<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\Request;

class AdminReportController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'pending');

        $reports = Report::with('reporter:id,name')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        // Muat "reportable" secara manual -- morphTo lintas dua model beda
        // (Kos & KosReview) tidak selalu bisa di-eager-load rapi lewat
        // with('reportable') kalau salah satu relasinya sudah dihapus permanen,
        // jadi ambil satu-satu saja (jumlah laporan diperkirakan kecil).
        $reports->getCollection()->transform(function ($report) {
            $report->target = $report->reportable()->first();
            return $report;
        });

        return view('admin.reports.index', compact('reports', 'status'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string|in:reviewed,dismissed',
            'admin_note' => 'nullable|string|max:1000',
        ]);

        $report = Report::findOrFail($id);
        $report->update([
            'status' => $request->status,
            'admin_note' => $request->admin_note,
        ]);

        return back()->with('success', 'Laporan berhasil diperbarui.');
    }
}
