<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\SusResponse;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Form kuesioner SUS publik -- dibuka responden dari halaman uji coba
 * coba.koskita setelah selesai memakai aplikasi versi web. Tanpa login,
 * tapi responden wajib memakai email akun yang tadi didaftarkan di
 * aplikasi: jawaban jadi terhubung ke akun yang benar-benar mencoba
 * aplikasinya, dan satu akun tidak bisa mengisi dua kali.
 */
class SusQuestionnaireController extends Controller
{
    public function create()
    {
        return view('web.sus.create');
    }

    public function store(Request $request)
    {
        $rules = [
            'email' => 'required|email|max:255',
            'feedback' => 'nullable|string|max:2000',
        ];
        foreach (array_keys(SusResponse::QUESTIONS) as $num) {
            $rules["q$num"] = 'required|integer|min:1|max:5';
        }
        $validated = $request->validate($rules, [
            'q*.required' => 'Mohon jawab semua 10 pernyataan.',
        ]);

        $user = User::where('email', trim($validated['email']))->first();
        if (!$user) {
            return back()->withInput()->withErrors([
                'email' => 'Email ini belum terdaftar. Pakai email yang sama dengan saat kamu mendaftar akun di aplikasi KosKita.',
            ]);
        }
        if (SusResponse::where('user_id', $user->id)->exists()) {
            return back()->withInput()->withErrors([
                'email' => 'Akun ini sudah mengisi kuesioner. Terima kasih atas partisipasimu!',
            ]);
        }

        SusResponse::create([
            'user_id' => $user->id,
            'respondent_name' => $user->name,
            'respondent_email' => $user->email,
            'feedback' => $validated['feedback'] ?? null,
            ...collect($validated)->only(array_map(fn (int $num) => "q$num", array_keys(SusResponse::QUESTIONS)))->all(),
        ]);

        return redirect()->route('sus.thanks');
    }

    public function thanks()
    {
        return view('web.sus.thanks');
    }
}
