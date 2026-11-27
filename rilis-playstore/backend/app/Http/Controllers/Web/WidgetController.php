<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Kos;

/**
 * Widget pencarian kos yang bisa DITANAM di situs lain lewat <iframe> (mis.
 * halaman "Perumahan Mahasiswa" di situs kampus) -- kemampuan yang secara
 * struktural cuma dimiliki web; sebuah aplikasi terpasang tidak bisa
 * "ditaruh" di dalam website lain. Sengaja halaman MANDIRI (bukan extend
 * web.layouts.app) -- tanpa nav/footer/loader situs utama, supaya pas
 * ditanam di iframe kecil tidak terasa seperti situs di dalam situs.
 * Semua tautan hasil pencarian buka target="_top" (keluar dari iframe ke
 * situs KosKita penuh), widget ini murni titik masuk, bukan pengganti
 * pengalaman lengkap.
 */
class WidgetController extends Controller
{
    public function search()
    {
        $locations = Kos::select('location')->distinct()->orderBy('location')->pluck('location');

        $featured = Kos::with('images')
            ->withCount('reviews')
            ->get()
            ->sortByDesc(fn (Kos $kos) => [$kos->average_review_rating ?? 0, $kos->id])
            ->take(3)
            ->values();

        return view('web.widget.search', compact('locations', 'featured'));
    }
}
