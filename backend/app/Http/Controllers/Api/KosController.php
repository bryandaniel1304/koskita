<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kos;
use App\Models\UserInteraction;
use App\Services\OwnerResponseTime;
use App\Services\SearchLogService;
use Illuminate\Http\Request;

class KosController extends Controller
{
    protected const OWNER_COLUMNS = 'owner:id,name,owner_verification_status,owner_verified_at,qris_image_path';

    /**
     * Tampilkan semua kos
     */
    public function index(Request $request)
    {
        $query = Kos::with('facilities', 'rules', 'images', 'roomTypes', self::OWNER_COLUMNS, 'reviews.user:id,name');

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

        // Filter rentang budget jika ada
        if ($request->filled('budget_min')) {
            $query->where('price', '>=', (int) $request->budget_min);
        }
        if ($request->filled('budget_max')) {
            $query->where('price', '<=', (int) $request->budget_max);
        }

        // Filter fasilitas (harus punya SEMUA id fasilitas yang diminta,
        // bukan salah satu -- konsisten dengan filter serupa di katalog web).
        $facilityIds = array_filter((array) $request->input('facilities', []));
        foreach ($facilityIds as $facilityId) {
            $query->whereHas('facilities', fn ($q) => $q->where('facilities.id', $facilityId));
        }

        // Urutan hasil -- default tetap "terbaru" (perilaku lama) supaya
        // tidak ada breaking change buat client yang belum kirim param ini.
        match ($request->input('sort')) {
            'price_asc' => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'distance' => $query->orderBy('distance_to_campus', 'asc'),
            'rating' => $query->withAvg('reviews', 'rating')->orderByDesc('reviews_avg_rating'),
            default => $query->latest(),
        };

        // Dipaginasi (20/halaman) -- sebelumnya ->get() menarik SEMUA kos
        // yang cocok sekaligus lengkap dengan semua relasinya (fasilitas/
        // aturan/foto/pemilik/ulasan), yang makin berat seiring data
        // bertambah. Flutter (KosProvider.fetchKoses/fetchMoreKoses) sudah
        // disesuaikan buat baca bentuk respons paginator ini & infinite-
        // scroll ambil halaman berikutnya, bukan expect array polos lagi.
        $koses = $query->paginate(20);
        $this->attachResponseBadges($koses->getCollection());
        SearchLogService::logIfEmpty($request, $koses->total());

        return response()->json($koses);
    }

    /**
     * Tempel badge "Respons Cepat" ke tiap kos dalam koleksi -- dihitung
     * BATCH per pemilik unik (bukan query per baris) supaya tidak N+1.
     */
    protected function attachResponseBadges($koses): void
    {
        $ownerIds = $koses->pluck('owner_id')->filter()->unique()->values()->all();
        if (empty($ownerIds)) {
            return;
        }
        $averages = OwnerResponseTime::averagesFor($ownerIds);
        foreach ($koses as $kos) {
            $kos->setAttribute('owner_response_badge', OwnerResponseTime::badgeLabel($averages[$kos->owner_id] ?? null));
        }
    }

    /**
     * Bandingkan kos berdampingan -- padanan mobile dari fitur
     * "Bandingkan Kos" yang sudah ada di web (lihat WebKosController::compare
     * & catatan kenapa diurutkan di PHP bukan ORDER BY FIELD() SQL, supaya
     * tetap portable ke driver SQLite yang dipakai test suite). Tidak ada
     * batas jumlah bisnis -- 50 di bawah murni jaga-jaga anti-abuse.
     */
    public function compare(Request $request)
    {
        $ids = array_slice(array_filter(array_map('intval', explode(',', (string) $request->query('ids')))), 0, 50);

        if (count($ids) < 2) {
            return response()->json(['message' => 'Pilih minimal 2 kos dulu buat dibandingkan.'], 422);
        }

        $koses = Kos::with('facilities', 'rules', 'images', 'roomTypes', self::OWNER_COLUMNS, 'reviews.user:id,name')
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn ($kos) => array_search($kos->id, $ids))
            ->values();

        if ($koses->count() < 2) {
            return response()->json(['message' => 'Kos yang dipilih tidak ditemukan (mungkin sudah dihapus).'], 422);
        }

        $this->attachResponseBadges($koses);

        return response()->json(['koses' => $koses]);
    }

    /**
     * Tampilkan detail kos dan catat interaksi klik/view
     */
    public function show($id, Request $request)
    {
        $kos = Kos::with('facilities', 'rules', 'images', 'roomTypes', self::OWNER_COLUMNS, 'reviews.user:id,name')->findOrFail($id);
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
        $onWaitlist = false;
        $hasConfirmedBooking = false;
        if ($user) {
            $userInteraction = UserInteraction::where('user_id', $user->id)
                ->where('kos_id', $kos->id)
                ->first();
            $onWaitlist = \App\Models\Waitlist::where('user_id', $user->id)->where('kos_id', $kos->id)->exists();
            $hasConfirmedBooking = \App\Models\Booking::where('user_id', $user->id)->where('kos_id', $kos->id)
                ->whereIn('status', ['confirmed', 'completed'])->exists();
        }

        $averages = OwnerResponseTime::averagesFor([$kos->owner_id]);
        $kos->setAttribute('owner_response_badge', OwnerResponseTime::badgeLabel($averages[$kos->owner_id] ?? null));
        // Sinyal buat client tampilkan/sembunyikan tombol "Tulis Ulasan" --
        // ulasan yang SUDAH ADA selalu boleh diedit; ulasan BARU baru boleh
        // kalau masa sewanya di kos ini sudah selesai (lihat ReviewController).
        $alreadyReviewed = $user && $kos->reviews->contains('user_id', $user->id);
        $kos->setAttribute('can_review', $user && ($alreadyReviewed || \App\Models\Booking::userHasCompletedStayAt($user->id, $kos->id)));

        $recommendation = null;
        if ($user) {
            $recService = app(\App\Services\RecommendationService::class);
            $recommendation = $recService->getRecommendationForKos($user->id, $kos);
        }

        return response()->json([
            'kos' => $kos,
            'user_interaction' => $userInteraction,
            'similar' => $this->similarKoses($kos),
            'on_waitlist' => $onWaitlist,
            'recommendation' => $recommendation,
            // QRIS pemilik cuma dikirim kalau booking penyewa untuk kos ini
            // sudah dikonfirmasi -- bukan disebar ke semua orang yang lihat
            // listing (konsisten dengan perilaku di web).
            'qris_url' => ($hasConfirmedBooking && $kos->owner?->qris_image_path)
                ? \Illuminate\Support\Facades\Storage::disk('public')->url($kos->owner->qris_image_path)
                : null,
        ]);
    }

    /**
     * "Kos Serupa" -- lokasi sama ATAU harga dalam rentang ±30%, kos lain
     * (bukan diri sendiri), diurutkan yang paling dekat harganya duluan.
     * Dibatasi 6 supaya ringan ditampilkan sebagai list horizontal.
     */
    protected function similarKoses(Kos $kos)
    {
        $priceMin = (int) round($kos->price * 0.7);
        $priceMax = (int) round($kos->price * 1.3);

        return Kos::with('images')
            ->where('id', '!=', $kos->id)
            ->where(function ($q) use ($kos, $priceMin, $priceMax) {
                $q->where('location', $kos->location)
                  ->orWhereBetween('price', [$priceMin, $priceMax]);
            })
            ->orderByRaw('ABS(price - ?) ASC', [$kos->price])
            ->take(6)
            ->get(['id', 'name', 'price', 'location', 'image_url', 'gender_type', 'distance_to_campus']);
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
            // Simpan snapshot harga & kamar kosong SAAT difavoritkan --
            // dipakai bandingkan nanti buat notifikasi "harga turun"/"kamar
            // tersedia lagi" (lihat NotificationController).
            if ($request->boolean('is_favorite')) {
                $interaction->favorited_price_snapshot = $kos->price;
                $interaction->favorited_rooms_snapshot = $kos->available_rooms;
            }
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

        $koses = Kos::with('facilities', 'rules', 'images', 'roomTypes', self::OWNER_COLUMNS, 'reviews.user:id,name')
            ->whereHas('interactions', function ($query) use ($user) {
                $query->where('user_id', $user->id)->where('is_favorite', true);
            })
            ->get();
        $this->attachResponseBadges($koses);

        return response()->json($koses);
    }
}
