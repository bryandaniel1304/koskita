<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\HandlesKosPhotos;
use App\Http\Controllers\Controller;
use App\Models\Kos;
use App\Models\KosImage;
use App\Models\KosRoomType;
use App\Models\KosReview;
use App\Models\UserInteraction;
use App\Models\UserProfile;
use App\Services\Recommendation\ContentBasedFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OwnerKosController extends Controller
{
    use HandlesKosPhotos;

    public function index(Request $request)
    {
        $koses = Kos::with('facilities', 'rules', 'images', 'roomTypes', 'reviews')
            ->where('owner_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json($koses);
    }

    /**
     * Detail satu kos milik pemilik + statistik yang cuma relevan buat
     * pemiliknya sendiri (jumlah dilihat, difavoritkan, dirating) --
     * SENGAJA dibungkus {kos, stats} (bukan objek kos polos) supaya data
     * privat ini tidak pernah bocor ke endpoint publik /api/kos/{id}.
     */
    public function show(Request $request, $id)
    {
        $kos = $this->ownedKosOrFail($request, $id, ['facilities', 'rules', 'images', 'roomTypes', 'reviews.user:id,name']);

        $interactions = UserInteraction::where('kos_id', $kos->id)->get();

        return response()->json([
            'kos' => $kos,
            'stats' => [
                'total_views' => (int) $interactions->sum('click_count'),
                'total_favorites' => $interactions->where('is_favorite', true)->count(),
                'total_ratings' => $interactions->whereNotNull('rating')->count(),
                'avg_rating' => $interactions->whereNotNull('rating')->isEmpty()
                    ? null
                    : round($interactions->whereNotNull('rating')->avg('rating'), 1),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->kosAttributes($this->validated($request));

        $kos = Kos::create([
            ...$data,
            'owner_id' => $request->user()->id,
            'image_url' => $data['image_url'] ?? 'https://images.unsplash.com/photo-1522771739844-6a9f6d5f14af?auto=format&fit=crop&w=500&q=80',
        ]);

        $kos->facilities()->sync($request->input('facilities', []));
        $kos->rules()->sync($request->input('rules', []));
        $this->storeUploadedPhotos($kos, $request);

        return response()->json([
            'message' => 'Kos berhasil ditambahkan.',
            'kos' => $kos->load('facilities', 'rules', 'images'),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $kos = $this->ownedKosOrFail($request, $id);
        $data = $this->kosAttributes($this->validated($request));

        if (isset($data['total_rooms']) && $data['total_rooms'] < $kos->occupied_rooms) {
            return response()->json([
                'message' => "Jumlah kamar tidak boleh lebih kecil dari kamar yang sedang terisi ({$kos->occupied_rooms}).",
            ], 422);
        }

        $kos->update([
            ...$data,
            'image_url' => $data['image_url'] ?? $kos->image_url,
        ]);

        $kos->facilities()->sync($request->input('facilities', []));
        $kos->rules()->sync($request->input('rules', []));
        $this->storeUploadedPhotos($kos, $request);

        return response()->json([
            'message' => 'Kos berhasil diperbarui.',
            'kos' => $kos->load('facilities', 'rules', 'images'),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $kos = $this->ownedKosOrFail($request, $id);
        $kos->delete();

        return response()->json(['message' => 'Kos berhasil dihapus.']);
    }

    public function destroyImage(Request $request, $kosId, $imageId)
    {
        $kos = $this->ownedKosOrFail($request, $kosId);
        $image = KosImage::where('kos_id', $kos->id)->findOrFail($imageId);
        Storage::disk('public')->delete($image->path);
        $image->delete();

        return response()->json(['message' => 'Foto berhasil dihapus.']);
    }

    /**
     * CRUD breakdown tipe kamar OPSIONAL (mis. "Kamar AC" Rp1.8jt vs
     * "Kamar Standar" Rp1.2jt) -- MURNI tampilan buat calon penyewa,
     * TIDAK memengaruhi price/total_rooms/available_rooms kos itu sendiri
     * sama sekali (lihat catatan lengkap di KosRoomType/migration-nya).
     */
    protected function roomTypeRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'price' => 'required|integer|min:0',
            'total_rooms' => 'required|integer|min:0',
        ];
    }

    public function storeRoomType(Request $request, $id)
    {
        $kos = $this->ownedKosOrFail($request, $id);
        $validated = $request->validate($this->roomTypeRules());

        $roomType = $kos->roomTypes()->create($validated);

        return response()->json(['message' => 'Tipe kamar ditambahkan.', 'room_type' => $roomType], 201);
    }

    public function updateRoomType(Request $request, $kosId, $roomTypeId)
    {
        $kos = $this->ownedKosOrFail($request, $kosId);
        $roomType = KosRoomType::where('kos_id', $kos->id)->findOrFail($roomTypeId);

        $roomType->update($request->validate($this->roomTypeRules()));

        return response()->json(['message' => 'Tipe kamar diperbarui.', 'room_type' => $roomType]);
    }

    public function destroyRoomType(Request $request, $kosId, $roomTypeId)
    {
        $kos = $this->ownedKosOrFail($request, $kosId);
        KosRoomType::where('kos_id', $kos->id)->findOrFail($roomTypeId)->delete();

        return response()->json(['message' => 'Tipe kamar dihapus.']);
    }

    /**
     * Aktivitas mingguan (8 minggu terakhir) untuk grafik analitik dashboard
     * pemilik -- booking masuk & ulasan baru per minggu. SENGAJA cuma dua
     * metrik ini (bukan "jumlah dilihat per minggu") karena UserInteraction
     * cuma simpan total klik kumulatif per user-kos (bukan log per
     * kejadian), jadi tidak bisa dipecah per minggu secara akurat -- lebih
     * baik tampilkan yang datanya benar-benar valid daripada mengarang.
     */
    public function analytics(Request $request, $id)
    {
        $kos = $this->ownedKosOrFail($request, $id);
        $weeksBack = 8;
        $start = now()->startOfWeek()->subWeeks($weeksBack - 1);

        $bookingsByWeek = $kos->bookings()
            ->where('created_at', '>=', $start)
            ->get()
            ->groupBy(fn ($b) => $b->created_at->startOfWeek()->format('Y-m-d'));

        $reviewsByWeek = $kos->reviews()
            ->where('created_at', '>=', $start)
            ->get()
            ->groupBy(fn ($r) => $r->created_at->startOfWeek()->format('Y-m-d'));

        $weeks = [];
        for ($i = 0; $i < $weeksBack; $i++) {
            $weekStart = $start->copy()->addWeeks($i);
            $key = $weekStart->format('Y-m-d');
            $weeks[] = [
                'week_start' => $key,
                'label' => $weekStart->translatedFormat('d M'),
                'bookings' => $bookingsByWeek->get($key, collect())->count(),
                'reviews' => $reviewsByWeek->get($key, collect())->count(),
            ];
        }

        return response()->json(['weeks' => $weeks]);
    }

    /**
     * Balasan pemilik atas satu ulasan -- hanya untuk ulasan di kos milik
     * pemilik yang sedang login (dicek lewat relasi kos->owner_id, BUKAN
     * asumsi kepemilikan dari input klien). Kirim string kosong untuk
     * menghapus balasan yang sudah ada.
     */
    public function replyToReview(Request $request, $id)
    {
        $request->validate([
            'reply' => 'nullable|string|max:1000',
        ]);

        $review = KosReview::with('kos')
            ->whereHas('kos', fn ($q) => $q->where('owner_id', $request->user()->id))
            ->find($id);

        if (!$review) {
            return response()->json(['message' => 'Ulasan tidak ditemukan.'], 404);
        }

        $reply = trim((string) $request->input('reply', ''));
        $review->update([
            'owner_reply' => $reply !== '' ? $reply : null,
            'owner_replied_at' => $reply !== '' ? now() : null,
        ]);

        return response()->json(['message' => 'Balasan berhasil disimpan.', 'review' => $review]);
    }

    /**
     * Kebalikan dari /recommendations: untuk kos milik pemilik ini, cari
     * profil penyewa (role "user") yang paling cocok, dipakai halaman
     * "Rekomendasi Penyewa" di app.
     */
    public function matches(Request $request, $id, ContentBasedFilter $cb)
    {
        $kos = $this->ownedKosOrFail($request, $id, ['facilities', 'rules']);

        $profiles = UserProfile::with('user')
            ->whereHas('user', fn ($q) => $q->where('role', 'user'))
            ->get();

        $scores = $cb->calculateScoresForKos($kos, $profiles);
        arsort($scores);
        $top = array_slice($scores, 0, 10, true);

        $matches = [];
        foreach ($top as $userId => $score) {
            $profile = $profiles->firstWhere('user_id', $userId);
            if (!$profile || !$profile->user) {
                continue;
            }
            $matches[] = [
                'user' => ['id' => $profile->user->id, 'name' => $profile->user->name],
                'gender' => $profile->gender,
                'occupation' => $profile->occupation,
                'budget_min' => $profile->budget_min,
                'budget_max' => $profile->budget_max,
                'preferred_location' => $profile->preferred_location,
                'preferred_facilities' => $profile->preferred_facilities,
                'preferred_rules' => $profile->preferred_rules,
                'match_percentage' => round($score * 100),
            ];
        }

        return response()->json(['matches' => $matches]);
    }

    protected function ownedKosOrFail(Request $request, $id, array $with = [])
    {
        $query = Kos::where('owner_id', $request->user()->id);
        if (!empty($with)) {
            $query->with($with);
        }
        return $query->findOrFail($id);
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|integer|min:0',
            'gender_type' => 'required|string|in:putra,putri,campur',
            'location' => 'required|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'distance_to_campus' => 'required|numeric|min:0',
            'total_rooms' => 'required|integer|min:1|max:255',
            'description' => 'nullable|string',
            'image_url' => 'nullable|url|max:2048',
            'facilities' => 'nullable|array',
            'rules' => 'nullable|array',
            'photos' => 'nullable|array',
            'photos.*' => 'image|max:4096',
        ]);
    }

    /**
     * Persempit hasil validasi ke kolom Kos saja -- 'facilities'/'rules'/
     * 'photos' ditangani terpisah lewat relasi & upload, bukan kolom tabel.
     */
    protected function kosAttributes(array $validated): array
    {
        return collect($validated)
            ->only(['name', 'price', 'gender_type', 'location', 'latitude', 'longitude', 'distance_to_campus', 'total_rooms', 'description', 'image_url'])
            ->all();
    }
}
