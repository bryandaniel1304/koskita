<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kos;
use App\Models\UserInteraction;
use Illuminate\Http\Request;

class KosController extends Controller
{
    /**
     * Tampilkan semua kos
     */
    public function index(Request $request)
    {
        $query = Kos::with('facilities', 'rules', 'images');

        // Filter pencarian sederhana jika ada query
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            });
        }

        // Filter tipe gender jika ada
        if ($request->has('gender_type')) {
            $query->where('gender_type', $request->gender_type);
        }

        // Filter lokasi jika ada
        if ($request->has('location')) {
            $query->where('location', $request->location);
        }

        $koses = $query->get();

        return response()->json($koses);
    }

    /**
     * Tampilkan detail kos dan catat interaksi klik/view
     */
    public function show($id, Request $request)
    {
        $kos = Kos::with('facilities', 'rules', 'images')->findOrFail($id);
        $user = $request->user();

        if ($user) {
            // Catat interaksi klik
            $interaction = UserInteraction::firstOrNew([
                'user_id' => $user->id,
                'kos_id' => $kos->id
            ]);

            $interaction->click_count = ($interaction->click_count ?? 0) + 1;
            $interaction->save();
        }

        // Dapatkan interaksi spesifik user ini terhadap kos ini (jika ada) untuk dikembalikan ke frontend
        $userInteraction = null;
        if ($user) {
            $userInteraction = UserInteraction::where('user_id', $user->id)
                ->where('kos_id', $kos->id)
                ->first();
        }

        return response()->json([
            'kos' => $kos,
            'user_interaction' => $userInteraction
        ]);
    }

    /**
     * Berikan rating atau favoritkan kos
     */
    public function rate($id, Request $request)
    {
        $request->validate([
            'rating' => 'nullable|integer|min:1|max:5',
            'is_favorite' => 'nullable|boolean',
        ]);

        $kos = Kos::findOrFail($id);
        $user = $request->user();

        $interaction = UserInteraction::firstOrCreate([
            'user_id' => $user->id,
            'kos_id' => $kos->id
        ]);

        if ($request->has('rating')) {
            $interaction->rating = $request->rating;
        }

        if ($request->has('is_favorite')) {
            $interaction->is_favorite = $request->is_favorite;
        }

        $interaction->save();

        return response()->json([
            'message' => 'Interaksi berhasil disimpan.',
            'interaction' => $interaction
        ]);
    }

    /**
     * Daftar kos yang difavoritkan pengguna login
     */
    public function favorites(Request $request)
    {
        $user = $request->user();

        $koses = Kos::with('facilities', 'rules', 'images')
            ->whereHas('interactions', function ($query) use ($user) {
                $query->where('user_id', $user->id)->where('is_favorite', true);
            })
            ->get();

        return response()->json($koses);
    }
}
