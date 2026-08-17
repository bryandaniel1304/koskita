<?php

namespace App\Http\Controllers;

use App\Models\Source\SourceUser;

class RespondentController extends Controller
{
    /**
     * Daftar 150 responden beserta ringkasan interaksinya -- dipakai untuk
     * analisis demografis Bab IV skripsi.
     */
    public function index()
    {
        $respondents = SourceUser::where('role', 'user')
            ->with(['profile', 'interactions'])
            ->orderBy('name')
            ->paginate(30);

        return view('respondents.index', compact('respondents'));
    }
}
