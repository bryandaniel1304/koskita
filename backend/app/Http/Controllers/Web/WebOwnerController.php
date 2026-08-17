<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Kos;
use App\Models\UserInteraction;
use App\Notifications\BookingStatusChanged;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Portal Pemilik versi situs -- MVP read/kelola dasar lewat browser untuk
 * pemilik yang tidak selalu pegang HP dengan aplikasi terpasang (mis. lagi
 * di depan komputer). Sengaja read-mostly: tambah/edit kos dengan upload
 * banyak foto tetap lewat aplikasi Flutter (form-nya sudah matang di sana);
 * di sini fokus ke hal yang paling sering dicek dari browser -- ringkasan,
 * status booking, dan balas ulasan.
 */
class WebOwnerController extends Controller
{
    public function dashboard(Request $request)
    {
        $ownerId = $request->user()->id;
        $kosIds = Kos::where('owner_id', $ownerId)->pluck('id');

        $interactions = UserInteraction::whereIn('kos_id', $kosIds)->get();
        $bookings = Booking::with('kos:id,price')->whereIn('kos_id', $kosIds)->get();

        $stats = [
            'total_kos' => $kosIds->count(),
            'total_views' => (int) $interactions->sum('click_count'),
            'total_favorites' => $interactions->where('is_favorite', true)->count(),
            'pending_bookings' => $bookings->where('status', 'pending')->count(),
            'confirmed_bookings' => $bookings->where('status', 'confirmed')->count(),
            'unpaid_confirmed' => $bookings->whereIn('status', ['confirmed', 'completed'])->where('payment_status', 'unpaid')->count(),
            // Pendapatan bulan ini -- dijumlahkan dari harga kos tiap booking
            // yang DITANDAI LUNAS bulan ini, bukan dari sistem pembayaran
            // sungguhan (KosKita tidak memproses transaksi finansial apapun).
            'revenue_this_month' => $bookings->where('payment_status', 'paid')
                ->filter(fn ($b) => $b->paid_at && $b->paid_at->isCurrentMonth())
                ->reduce(fn ($sum, $b) => $sum + ($b->kos->price ?? 0), 0),
        ];

        $recentBookings = Booking::with('kos', 'user')
            ->whereIn('kos_id', $kosIds)
            ->latest()
            ->take(5)
            ->get();

        // 'images' WAJIB dimuat -- accessor cover_image (dipakai di view)
        // lazy-load relasi images kalau belum ada, jadi tanpa eager-load
        // ini jadi N+1 query (satu query images tambahan PER kos yang
        // ditampilkan).
        $koses = Kos::withCount(['bookings as pending_bookings_count' => fn ($q) => $q->where('status', 'pending')])
            ->with('images')
            ->where('owner_id', $ownerId)
            ->latest()
            ->take(5)
            ->get();

        return view('web.owner.dashboard', compact('stats', 'recentBookings', 'koses'));
    }

    public function koses(Request $request)
    {
        $koses = Kos::withCount(['bookings as pending_bookings_count' => fn ($q) => $q->where('status', 'pending')])
            ->with('images')
            ->where('owner_id', $request->user()->id)
            ->latest()
            ->get();

        // Statistik ringan per kos (views/favorit/rating) -- dihitung batch
        // biar tidak N+1 query per kartu kos.
        $interactionsByKos = UserInteraction::whereIn('kos_id', $koses->pluck('id'))
            ->get()
            ->groupBy('kos_id');

        $kosStats = $koses->mapWithKeys(function ($kos) use ($interactionsByKos) {
            $rows = $interactionsByKos->get($kos->id, collect());
            return [$kos->id => [
                'views' => (int) $rows->sum('click_count'),
                'favorites' => $rows->where('is_favorite', true)->count(),
                'avg_rating' => $rows->whereNotNull('rating')->isEmpty() ? null : round($rows->whereNotNull('rating')->avg('rating'), 1),
            ]];
        });

        return view('web.owner.koses.index', compact('koses', 'kosStats'));
    }

    /**
     * Ekspor kos milik sendiri ke CSV -- pola "kerja skala besar di layar
     * besar": pemilik dengan banyak kos edit harga/kamar sekaligus lewat
     * spreadsheet, lalu unggah balik lewat importCsv(). Jauh lebih praktis
     * di komputer daripada buka form satu-satu di HP.
     */
    public function exportCsv(Request $request)
    {
        $koses = Kos::where('owner_id', $request->user()->id)->orderBy('name')->get();
        $filename = 'kos-koskita-' . now()->format('Y-m-d_H-i-s') . '.csv';

        return response()->streamDownload(function () use ($koses) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'nama', 'harga', 'total_kamar', 'kamar_terisi', 'lokasi', 'tipe']);
            foreach ($koses as $kos) {
                fputcsv($out, [$kos->id, $kos->name, $kos->price, $kos->total_rooms, $kos->occupied_rooms, $kos->location, $kos->gender_type]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Impor massal -- SENGAJA cuma boleh UPDATE kos yang sudah ada milik
     * sendiri (dicocokkan lewat kolom id, di-scope ke owner_id), bukan bikin
     * kos baru lewat CSV -- kos baru butuh foto & fasilitas yang tidak
     * masuk akal diisi lewat spreadsheet. Kolom yang dipakai cuma harga &
     * total_kamar (dua hal yang paling sering di-update massal di akhir
     * bulan/awal semester).
     */
    public function importCsv(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:2048']);

        $ownerId = $request->user()->id;
        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = fgetcsv($handle);

        $idCol = array_search('id', $header);
        $priceCol = array_search('harga', $header);
        $roomsCol = array_search('total_kamar', $header);

        if ($idCol === false || ($priceCol === false && $roomsCol === false)) {
            fclose($handle);
            return back()->withErrors(['file' => 'Format CSV tidak dikenali. Gunakan file hasil ekspor dari halaman ini (kolom minimal: id, harga, total_kamar).']);
        }

        $updated = 0;
        $skipped = [];
        $rowNum = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            $id = $row[$idCol] ?? null;
            if (!$id) {
                continue;
            }

            $kos = Kos::where('id', $id)->where('owner_id', $ownerId)->first();
            if (!$kos) {
                $skipped[] = "Baris $rowNum: kos #$id tidak ditemukan atau bukan milikmu.";
                continue;
            }

            $changes = [];
            if ($priceCol !== false && is_numeric($row[$priceCol] ?? null)) {
                $changes['price'] = (int) $row[$priceCol];
            }
            if ($roomsCol !== false && is_numeric($row[$roomsCol] ?? null)) {
                $newTotal = (int) $row[$roomsCol];
                if ($newTotal < $kos->occupied_rooms) {
                    $skipped[] = "Baris $rowNum ({$kos->name}): total kamar ($newTotal) tidak boleh kurang dari kamar terisi ({$kos->occupied_rooms}).";
                } else {
                    $changes['total_rooms'] = $newTotal;
                }
            }

            if (!empty($changes)) {
                $kos->update($changes);
                $updated++;
            }
        }
        fclose($handle);

        $status = "$updated kos berhasil diperbarui.";
        if (!empty($skipped)) {
            return back()->with('status', $status)->withErrors(['import' => implode(' ', array_slice($skipped, 0, 5))]);
        }

        return back()->with('status', $status);
    }

    public function kosShow(Request $request, $id)
    {
        $kos = Kos::with(['facilities', 'rules', 'images', 'reviews.user:id,name'])
            ->where('owner_id', $request->user()->id)
            ->findOrFail($id);

        $interactions = UserInteraction::where('kos_id', $kos->id)->get();
        $stats = [
            'total_views' => (int) $interactions->sum('click_count'),
            'total_favorites' => $interactions->where('is_favorite', true)->count(),
            'total_ratings' => $interactions->whereNotNull('rating')->count(),
            'avg_rating' => $interactions->whereNotNull('rating')->isEmpty()
                ? null
                : round($interactions->whereNotNull('rating')->avg('rating'), 1),
        ];

        $bookings = Booking::with('user')->where('kos_id', $kos->id)->latest()->get();

        return view('web.owner.koses.show', compact('kos', 'stats', 'bookings'));
    }

    /**
     * Balas ulasan langsung dari halaman detail kos di portal -- alur bisnis
     * & validasinya sama persis dengan Api\OwnerKosController::replyToReview,
     * cuma medium beda (form POST + redirect, bukan JSON).
     */
    public function replyToReview(Request $request, $kosId, $reviewId)
    {
        $request->validate(['reply' => 'nullable|string|max:1000']);

        $kos = Kos::where('owner_id', $request->user()->id)->findOrFail($kosId);
        $review = $kos->reviews()->findOrFail($reviewId);

        $reply = trim((string) $request->input('reply', ''));
        $review->update([
            'owner_reply' => $reply !== '' ? $reply : null,
            'owner_replied_at' => $reply !== '' ? now() : null,
        ]);

        return back()->with('status', $reply !== '' ? 'Balasan berhasil disimpan.' : 'Balasan dihapus.');
    }

    public function bookings(Request $request)
    {
        $status = $request->query('status');
        $kosIds = Kos::where('owner_id', $request->user()->id)->pluck('id');

        $bookings = Booking::with('kos', 'user')
            ->whereIn('kos_id', $kosIds)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->get();

        return view('web.owner.bookings.index', compact('bookings', 'status'));
    }

    /**
     * Ubah status booking (confirmed/rejected/completed) -- validasi &
     * guard overbooking sama persis dengan Api\OwnerBookingController::update.
     */
    public function updateBookingStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string|in:confirmed,rejected,completed',
        ]);

        $booking = Booking::with('kos', 'user')
            ->whereHas('kos', fn ($q) => $q->where('owner_id', $request->user()->id))
            ->findOrFail($id);
        $previousStatus = $booking->status;

        if ($request->status === 'confirmed' && $booking->status !== 'confirmed' && !$booking->kos->hasAvailableRoom()) {
            return back()->withErrors(['booking' => 'Tidak bisa dikonfirmasi -- semua kamar di kos ini sudah penuh terisi.']);
        }

        $booking->update(['status' => $request->status]);

        if ($previousStatus !== $booking->status && $booking->user) {
            try {
                $booking->user->notify(new BookingStatusChanged($booking));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return back()->with('status', 'Status booking berhasil diperbarui.');
    }

    /**
     * Tandai booking sudah/belum dibayar -- PENANDA MANUAL oleh pemilik,
     * sama persis dengan Api\OwnerBookingController::updatePaymentStatus
     * (KosKita tidak memproses transaksi finansial apapun).
     */
    public function updateBookingPayment(Request $request, $id)
    {
        $request->validate([
            'payment_status' => 'required|string|in:unpaid,paid',
        ]);

        $booking = Booking::whereHas('kos', fn ($q) => $q->where('owner_id', $request->user()->id))
            ->findOrFail($id);

        $isPaid = $request->payment_status === 'paid';
        $booking->update([
            'payment_status' => $request->payment_status,
            'paid_at' => $isPaid ? now() : null,
        ]);

        return back()->with('status', $isPaid ? 'Booking ditandai sudah dibayar.' : 'Booking ditandai belum dibayar.');
    }

    /**
     * Tandai beberapa booking lunas sekaligus -- berguna di akhir bulan
     * saat pemilik memproses banyak pembayaran tunai/transfer manual sekaligus.
     */
    public function bulkMarkPaid(Request $request)
    {
        $request->validate([
            'booking_ids' => 'required|array|min:1',
            'booking_ids.*' => 'integer',
        ]);

        $count = Booking::whereIn('id', $request->booking_ids)
            ->whereHas('kos', fn ($q) => $q->where('owner_id', $request->user()->id))
            ->update(['payment_status' => 'paid', 'paid_at' => now()]);

        return back()->with('status', "{$count} booking ditandai lunas sekaligus.");
    }

    /**
     * Corong konversi (dilihat -> difavoritkan -> booking diajukan ->
     * dikonfirmasi) + timeline okupansi 6 bulan terakhir -- keduanya
     * dihitung dari data yang sudah ada, tanpa tabel/state baru.
     */
    public function analytics(Request $request)
    {
        $ownerId = $request->user()->id;
        $kosIds = Kos::where('owner_id', $ownerId)->pluck('id');

        $interactions = UserInteraction::whereIn('kos_id', $kosIds)->get();
        $bookings = Booking::whereIn('kos_id', $kosIds)->get();

        $funnel = [
            'views' => (int) $interactions->sum('click_count'),
            'favorites' => $interactions->where('is_favorite', true)->count(),
            'bookings_submitted' => $bookings->count(),
            'bookings_confirmed' => $bookings->whereIn('status', ['confirmed', 'completed'])->count(),
        ];

        // Timeline okupansi -- untuk 6 bulan terakhir, hitung berapa booking
        // aktif (confirmed/completed) yang rentang tanggalnya menyentuh bulan
        // itu. Pakai tanggal mulai bulan sebagai penanda, bukan kalender
        // per-kamar (skema saat ini belum granular sampai level kamar).
        $activeBookings = $bookings->whereIn('status', ['confirmed', 'completed']);
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = now()->subMonths($i)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();
            $overlapping = $activeBookings->filter(function ($b) use ($monthStart, $monthEnd) {
                $bookingEnd = $b->start_date->copy()->addMonths($b->duration_months);
                return $b->start_date->lte($monthEnd) && $bookingEnd->gte($monthStart);
            })->count();
            $months[] = ['label' => $monthStart->translatedFormat('M Y'), 'count' => $overlapping];
        }

        return view('web.owner.analytics', compact('funnel', 'months'));
    }

    public function settings(Request $request)
    {
        return view('web.owner.settings', ['owner' => $request->user()]);
    }

    /**
     * Kirim dokumen identitas untuk badge "Pemilik Terverifikasi" -- alur
     * & validasinya sama dengan Api\OwnerVerificationController::submit.
     */
    public function submitVerification(Request $request)
    {
        $request->validate(['document' => 'required|image|max:4096']);

        $user = $request->user();
        if ($user->owner_verification_document) {
            Storage::disk('public')->delete($user->owner_verification_document);
        }

        $path = $request->file('document')->store('owner-verifications', 'public');
        $user->update([
            'owner_verification_document' => $path,
            'owner_verification_status' => 'pending',
            'owner_verified_at' => null,
        ]);

        return back()->with('status', 'Dokumen berhasil dikirim, menunggu peninjauan admin.');
    }

    public function uploadQris(Request $request)
    {
        $request->validate(['qris' => 'required|image|max:4096']);

        $user = $request->user();
        if ($user->qris_image_path) {
            Storage::disk('public')->delete($user->qris_image_path);
        }

        $user->update(['qris_image_path' => $request->file('qris')->store('owner-qris', 'public')]);

        return back()->with('status', 'Kode QRIS berhasil disimpan.');
    }

    public function deleteQris(Request $request)
    {
        $user = $request->user();
        if ($user->qris_image_path) {
            Storage::disk('public')->delete($user->qris_image_path);
            $user->update(['qris_image_path' => null]);
        }

        return back()->with('status', 'Kode QRIS dihapus.');
    }
}
