<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Facility;
use App\Models\Kos;
use App\Models\UserInteraction;
use App\Models\Waitlist;
use App\Services\OwnerResponseTime;
use App\Services\SearchLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WebKosController extends Controller
{
    /**
     * Katalog kos dengan filter -- versi web dari Api\KosController@index,
     * ditulis terpisah (bukan reuse langsung) karena butuh filter lebih
     * kaya (budget, fasilitas) + pagination utk tampilan grid situs,
     * sementara endpoint API dipertahankan persis seperti semula supaya
     * app Flutter yang sudah jalan tidak kena dampak apapun.
     */
    public function index(Request $request)
    {
        $query = Kos::with(['facilities', 'rules', 'images', 'roomTypes', 'reviews', 'owner:id,name,owner_verification_status,owner_verified_at']);

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }

        if ($location = $request->string('location')->trim()->value()) {
            $query->where('location', $location);
        }

        if ($gender = $request->string('gender_type')->trim()->value()) {
            $query->where('gender_type', $gender);
        }

        if ($request->filled('budget_min')) {
            $query->where('price', '>=', (int) $request->input('budget_min'));
        }

        if ($request->filled('budget_max')) {
            $query->where('price', '<=', (int) $request->input('budget_max'));
        }

        $facilityIds = array_filter((array) $request->input('facilities', []));
        if (!empty($facilityIds)) {
            foreach ($facilityIds as $facilityId) {
                $query->whereHas('facilities', fn ($q) => $q->where('facilities.id', $facilityId));
            }
        }

        $sort = $request->string('sort')->value() ?: 'terbaru';
        match ($sort) {
            'harga_termurah' => $query->orderBy('price', 'asc'),
            'harga_termahal' => $query->orderBy('price', 'desc'),
            'jarak' => $query->orderBy('distance_to_campus', 'asc'),
            default => $query->latest(),
        };

        $koses = $query->paginate(9)->withQueryString();
        SearchLogService::logIfEmpty($request, $koses->total());

        $ownerIds = $koses->getCollection()->pluck('owner_id')->filter()->unique()->values()->all();
        $responseAverages = OwnerResponseTime::averagesFor($ownerIds);
        $koses->getCollection()->each(function ($kos) use ($responseAverages) {
            $kos->owner_response_badge = OwnerResponseTime::badgeLabel($responseAverages[$kos->owner_id] ?? null);
        });

        $locations = Kos::select('location')->distinct()->orderBy('location')->pluck('location');
        $facilities = Facility::orderBy('name')->get();

        return view('web.kos.index', [
            'koses' => $koses,
            'locations' => $locations,
            'facilities' => $facilities,
            'sort' => $sort,
            'selectedFacilities' => array_map('intval', $facilityIds),
        ]);
    }

    /**
     * Perbandingan sisi-berdampingan 2-3 kos -- versi web dari
     * compare_screen.dart di app Flutter (yang dipanggil dari Favorit),
     * di sini dipicu dari checkbox "Bandingkan" di katalog /kos lewat
     * query string ?ids=1,2,3 (bukan `extra` seperti go_router karena web
     * tidak punya mekanisme itu).
     */
    public function compare(Request $request)
    {
        // Tidak ada batas jumlah kos yang "wajar" secara bisnis -- pengguna
        // boleh bandingkan sebanyak yang mereka mau, tabelnya sudah bisa
        // discroll ke samping (lihat .table-responsive di compare.blade.php).
        // 50 di sini murni jaga-jaga anti-abuse (mis. seseorang comot query
        // string berisi ratusan id), bukan batas UX.
        $ids = array_slice(array_filter(array_map('intval', explode(',', (string) $request->query('ids')))), 0, 50);

        if (count($ids) < 2) {
            return redirect()->route('web.kos.index')->withErrors(['compare' => 'Pilih minimal 2 kos dulu buat dibandingkan.']);
        }

        // Diurutkan ulang di PHP (bukan ORDER BY FIELD() SQL) supaya kolom
        // hasil perbandingan konsisten sama urutan checkbox yang dicentang
        // -- FIELD() cuma ada di MySQL/MariaDB, tidak portable ke driver
        // lain (mis. SQLite yang dipakai test suite).
        $koses = Kos::with(['facilities'])
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn ($kos) => array_search($kos->id, $ids))
            ->values();

        if ($koses->count() < 2) {
            return redirect()->route('web.kos.index')->withErrors(['compare' => 'Kos yang dipilih tidak ditemukan (mungkin sudah dihapus).']);
        }

        return view('web.kos.compare', ['koses' => $koses]);
    }

    /**
     * Detail kos + catat interaksi klik (samasama seperti Api\KosController@show)
     * kalau pengunjung sedang login, supaya kos ini juga ikut jadi sinyal
     * buat Collaborative Filtering walau ditemukan lewat situs, bukan app.
     */
    public function show(Request $request, $id)
    {
        $kos = Kos::with(['facilities', 'rules', 'images', 'roomTypes', 'owner:id,name,owner_verification_status,owner_verified_at', 'reviews.user:id,name'])->findOrFail($id);
        $kos->owner_response_badge = OwnerResponseTime::badgeLabel(OwnerResponseTime::averagesFor([$kos->owner_id])[$kos->owner_id] ?? null);

        $user = $request->user();
        $myInteraction = null;
        $myReview = null;
        $onWaitlist = false;
        $confirmedBooking = null;
        $canReview = false;

        if ($user) {
            $interaction = UserInteraction::firstOrNew(['user_id' => $user->id, 'kos_id' => $kos->id]);
            $interaction->click_count = ($interaction->click_count ?? 0) + 1;
            $interaction->save();
            $myInteraction = $interaction;
            $myReview = $kos->reviews->firstWhere('user_id', $user->id);
            $onWaitlist = Waitlist::where('user_id', $user->id)->where('kos_id', $kos->id)->exists();
            // QRIS & bukti booking tetap tampil begitu DIKONFIRMASI (bukan
            // nunggu selesai) -- itu memang soal pembayaran masa sewa yang
            // sedang berjalan, beda syarat dengan boleh-menulis-ulasan.
            $confirmedBooking = Booking::where('user_id', $user->id)->where('kos_id', $kos->id)
                ->whereIn('status', ['confirmed', 'completed'])->latest()->first();
            // Tapi menulis ULASAN BARU cuma boleh setelah masa sewa selesai
            // (lihat Booking::userHasCompletedStayAt) -- ulasan yang sudah
            // ada ($myReview) selalu boleh diedit terlepas dari status ini.
            $canReview = (bool) $myReview || Booking::userHasCompletedStayAt($user->id, $kos->id);
        }

        $similar = Kos::where('id', '!=', $kos->id)
            ->where('location', $kos->location)
            ->with(['images', 'reviews'])
            ->take(3)
            ->get();

        $kosStructuredData = $this->kosStructuredData($kos);

        return view('web.kos.show', compact('kos', 'myInteraction', 'myReview', 'similar', 'onWaitlist', 'confirmedBooking', 'canReview', 'kosStructuredData'));
    }

    /**
     * JSON-LD (schema.org Product+Offer) siap-pakai untuk rich snippet
     * Google -- SENGAJA dibangun & di-encode di sini (bukan langsung di
     * Blade lewat json_encode([...]) inline) karena literal string
     * '@context'/'@type' di dalam array PHP yang ditulis LANGSUNG di file
     * .blade.php kena tangkap compiler Blade sebagai directive @context
     * (fitur Laravel Context sejak v11) dan meledak jadi PHP korup --
     * kasus yang persis sama dengan comment "(Blade @json)" yang pernah
     * kena masalah serupa di research-dashboard.
     */
    protected function kosStructuredData(Kos $kos): string
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $kos->name,
            'description' => \Illuminate\Support\Str::limit(strip_tags($kos->description ?? ''), 300),
            'image' => $kos->images->isNotEmpty() ? $kos->images->pluck('url')->all() : [$kos->cover_image],
            'offers' => [
                '@type' => 'Offer',
                'price' => $kos->price,
                'priceCurrency' => 'IDR',
                'priceValidUntil' => now()->addYear()->toDateString(),
                'availability' => $kos->available_rooms > 0 ? 'https://schema.org/InStock' : 'https://schema.org/SoldOut',
                'url' => url()->current(),
            ],
        ];

        if (($kos->reviews_count ?? 0) > 0) {
            $data['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $kos->average_review_rating,
                'reviewCount' => $kos->reviews_count,
            ];
        }

        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Sama persis logikanya dengan Api\ReviewController@store (dipakai
     * mobile) -- ulasan baru cuma dari penyewa yang masa sewanya untuk kos
     * ini SUDAH SELESAI (lihat Booking::userHasCompletedStayAt(), bukan
     * cuma "confirmed"), ulasan lama boleh diedit bebas, dan sekarang juga
     * bisa lampirkan foto sama seperti di app.
     */
    public function storeReview(Request $request, $id)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
            'photo' => 'nullable|image|max:4096',
        ]);

        $kos = Kos::findOrFail($id);
        $user = $request->user();

        $existing = $kos->reviews()->where('user_id', $user->id)->first();

        if (!$existing && !Booking::userHasCompletedStayAt($user->id, $kos->id)) {
            return back()->withErrors(['review' => 'Kamu hanya bisa memberi ulasan setelah masa sewamu di kos ini selesai.']);
        }

        $data = ['rating' => $request->integer('rating'), 'comment' => $request->input('comment')];

        if ($request->hasFile('photo')) {
            if ($existing && $existing->photo_path) {
                Storage::disk('public')->delete($existing->photo_path);
            }
            $data['photo_path'] = $request->file('photo')->store('review-photos', 'public');
        }

        $kos->reviews()->updateOrCreate(['user_id' => $user->id], $data);

        return back()->with('status', 'Ulasan kamu berhasil disimpan. Terima kasih!');
    }

    public function toggleFavorite(Request $request, $id)
    {
        $kos = Kos::findOrFail($id);
        $interaction = UserInteraction::firstOrCreate(['user_id' => $request->user()->id, 'kos_id' => $kos->id]);
        $interaction->is_favorite = !$interaction->is_favorite;
        if ($interaction->is_favorite) {
            $interaction->favorited_price_snapshot = $kos->price;
            $interaction->favorited_rooms_snapshot = $kos->available_rooms;
        }
        $interaction->save();

        return back()->with('status', $interaction->is_favorite ? 'Kos ditambahkan ke favorit.' : 'Kos dihapus dari favorit.');
    }

    public function toggleWaitlist(Request $request, $id)
    {
        $kos = Kos::findOrFail($id);
        $existing = Waitlist::where('user_id', $request->user()->id)->where('kos_id', $kos->id)->first();

        if ($existing) {
            $existing->delete();
            return back()->with('status', 'Kamu keluar dari daftar tunggu.');
        }

        Waitlist::create(['user_id' => $request->user()->id, 'kos_id' => $kos->id]);
        return back()->with('status', 'Kamu akan diberi tahu begitu kamar tersedia lagi.');
    }

    /**
     * Halaman arahan SEO per area (mis. /kos/lokasi/karawaci) -- URL statis
     * & bisa dihafal/dibagikan, beda dari hasil filter dinamis (?location=)
     * yang query string-nya panjang dan tidak menarik dijadikan target kata
     * kunci pencarian. Konten (H1 spesifik, jumlah kos, harga rata-rata)
     * juga sengaja unik per area supaya tidak dianggap Google sebagai
     * duplicate content dari katalog utama.
     */
    public function byLocation(Request $request, string $location)
    {
        $allLocations = Kos::select('location')->distinct()->pluck('location');
        $matchedLocation = $allLocations->first(fn ($loc) => \Illuminate\Support\Str::slug($loc) === $location);

        if (!$matchedLocation) {
            abort(404);
        }

        $query = Kos::with(['facilities', 'rules', 'images', 'reviews'])->where('location', $matchedLocation);

        $sort = $request->string('sort')->value() ?: 'terbaru';
        match ($sort) {
            'harga_termurah' => $query->orderBy('price', 'asc'),
            'harga_termahal' => $query->orderBy('price', 'desc'),
            'jarak' => $query->orderBy('distance_to_campus', 'asc'),
            default => $query->latest(),
        };

        $koses = $query->paginate(9)->withQueryString();

        $stats = [
            'total' => Kos::where('location', $matchedLocation)->count(),
            'avg_price' => (int) Kos::where('location', $matchedLocation)->avg('price'),
            'min_price' => (int) Kos::where('location', $matchedLocation)->min('price'),
        ];

        return view('web.kos.location', compact('koses', 'matchedLocation', 'location', 'sort', 'stats'));
    }

    public function report(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:255',
            'details' => 'nullable|string|max:1000',
        ]);

        $kos = Kos::findOrFail($id);

        \App\Models\Report::create([
            'reportable_type' => Kos::class,
            'reportable_id' => $kos->id,
            'reporter_id' => $request->user()->id,
            'reason' => $request->reason,
            'details' => $request->details,
            'status' => 'pending',
        ]);

        return back()->with('status', 'Laporan terkirim, terima kasih. Tim kami akan meninjaunya.');
    }
}
