<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Kos;
use Illuminate\Http\Response;

/**
 * sitemap.xml dinamis -- daftar SEMUA kos + halaman statis penting, supaya
 * Google (dan mesin pencari lain) bisa temukan & indeks tiap listing tanpa
 * perlu merayapi seluruh situs lewat tautan satu-satu. Ini murni kemampuan
 * web (aplikasi terpasang tidak pernah dirayapi mesin pencari sama sekali).
 */
class SitemapController extends Controller
{
    public function index(): Response
    {
        $koses = Kos::select('id', 'updated_at')->orderByDesc('updated_at')->get();
        $locations = Kos::select('location')->distinct()->pluck('location');

        $urls = collect();

        $urls->push(['loc' => route('web.home'), 'changefreq' => 'daily', 'priority' => '1.0']);
        $urls->push(['loc' => route('web.kos.index'), 'changefreq' => 'daily', 'priority' => '0.9']);
        $urls->push(['loc' => route('web.tips.index'), 'changefreq' => 'weekly', 'priority' => '0.6']);
        $urls->push(['loc' => route('legal.privacy'), 'changefreq' => 'yearly', 'priority' => '0.3']);
        $urls->push(['loc' => route('legal.terms'), 'changefreq' => 'yearly', 'priority' => '0.3']);

        foreach ($locations as $location) {
            $urls->push([
                'loc' => route('web.kos.location', ['location' => \Illuminate\Support\Str::slug($location)]),
                'changefreq' => 'daily',
                'priority' => '0.8',
            ]);
        }

        $articles = \App\Models\Article::published()->get(['slug', 'updated_at']);
        foreach ($articles as $article) {
            $urls->push([
                'loc' => route('web.tips.show', $article->slug),
                'lastmod' => $article->updated_at?->toAtomString(),
                'changefreq' => 'monthly',
                'priority' => '0.5',
            ]);
        }

        foreach ($koses as $kos) {
            $urls->push([
                'loc' => route('web.kos.show', $kos->id),
                'lastmod' => $kos->updated_at?->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => '0.7',
            ]);
        }

        $xml = view('sitemap', compact('urls'))->render();

        return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
