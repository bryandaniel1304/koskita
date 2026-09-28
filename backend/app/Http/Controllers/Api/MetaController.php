<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\Rule;
use Illuminate\Http\Request;

class MetaController extends Controller
{
    /**
     * Daftar master data fasilitas & aturan -- dipakai form tambah/edit kos
     * milik pemilik kos di app, supaya string-nya persis sama dengan yang
     * dipakai algoritma rekomendasi (bukan diketik bebas), DAN oleh layar
     * onboarding preferensi penyewa.
     *
     * Urutannya berdasarkan seberapa banyak kos yang benar-benar memakainya,
     * bukan urutan id. Alasannya: onboarding cuma menampilkan sebagian
     * teratas, dan preferensi atas fasilitas yang tidak dimiliki kos manapun
     * tidak pernah bisa menaikkan skor Content-Based siapa pun -- jadi yang
     * paling berguna ditawarkan duluan.
     *
     * `limit` opsional supaya onboarding bisa minta 10 teratas saja,
     * sementara form pemilik kos tetap menerima daftar lengkap.
     */
    public function index(Request $request)
    {
        $request->validate(['limit' => 'nullable|integer|min:1|max:200']);
        $limit = $request->integer('limit') ?: null;

        return response()->json([
            'facilities' => $this->popular(Facility::query(), $limit),
            'rules' => $this->popular(Rule::query(), $limit),
        ]);
    }

    /** Urut dari yang paling banyak dipakai kos, lalu alfabetis sebagai penentu tetap. */
    protected function popular($query, ?int $limit)
    {
        return $query->withCount('koses')
            ->orderByDesc('koses_count')
            ->orderBy('name')
            ->when($limit, fn ($q) => $q->limit($limit))
            ->get(['id', 'name'])
            ->map(fn ($row) => ['id' => $row->id, 'name' => $row->name])
            ->values();
    }
}
