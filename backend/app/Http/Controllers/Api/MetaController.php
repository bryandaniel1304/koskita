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
     * dipakai algoritma rekomendasi (bukan diketik bebas).
     */
    public function index(Request $request)
    {
        return response()->json([
            'facilities' => Facility::orderBy('id')->get(['id', 'name']),
            'rules' => Rule::orderBy('id')->get(['id', 'name']),
        ]);
    }
}
