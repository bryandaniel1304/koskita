<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Scraper tipis untuk halaman detail kamar Mamikos (mamikos.com/room/...).
 * Halaman ini SERVER-RENDERED (SEO), data harga/fasilitas/gender/koordinat
 * sudah ada langsung di HTML mentah -- tidak perlu headless browser, tidak
 * membobol proteksi apa pun. Dipakai lewat `php artisan mamikos:scrape`.
 *
 * URL yang di-scrape didapat dari pencarian web (site:mamikos.com/room ...),
 * BUKAN dari endpoint pencarian internal Mamikos (halaman /cari mereka
 * client-rendered lewat Vue, sengaja tidak disentuh). robots.txt Mamikos
 * (`Allow: /`, hanya blokir /admin/ /unsubscribe/ /webview/) tidak melarang
 * jalur ini.
 *
 * Etika/rate-limit: satu request per URL, jeda di sisi caller (command),
 * User-Agent jujur (bukan menyamar jadi browser buat menyiasati sesuatu --
 * sekadar supaya request tidak ditolak server statis biasa).
 */
class MamikosService
{
    protected const USER_AGENT = 'KosKita-Skripsi-Research/1.0 (+riset tugas akhir, bukan bot komersial)';

    /**
     * @return array{name:?string,price_monthly:?int,gender:?string,lat:?float,lng:?float,facilities:array,available_room:?int,image_url:?string,area_city:?string,source_url:string}|null
     */
    public function fetchListing(string $url): ?array
    {
        $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
            ->timeout(10)
            ->get($url);

        if (!$response->successful()) {
            Log::warning('MamikosService::fetchListing gagal', ['url' => $url, 'status' => $response->status()]);
            return null;
        }

        $html = $response->body();

        return [
            'name' => $this->extractTitle($html),
            'price_monthly' => $this->extractInt($html, 'price_monthly'),
            'gender' => $this->extractGenderFromUrl($url),
            'lat' => $this->extractLatLng($html)['lat'] ?? null,
            'lng' => $this->extractLatLng($html)['lng'] ?? null,
            'facilities' => $this->extractFacilities($html),
            'available_room' => $this->extractInt($html, 'available_room'),
            'image_url' => $this->extractOgImage($html),
            'area_city' => $this->extractString($html, 'area_city'),
            'source_url' => $url,
        ];
    }

    protected function extractTitle(string $html): ?string
    {
        if (preg_match('/<title>([^<]*)<\/title>/', $html, $m)) {
            return trim(html_entity_decode($m[1]));
        }
        return null;
    }

    protected function extractInt(string $html, string $key): ?int
    {
        if (preg_match('/"' . preg_quote($key, '/') . '":(\d+)/', $html, $m)) {
            return (int) $m[1];
        }
        return null;
    }

    protected function extractString(string $html, string $key): ?string
    {
        if (preg_match('/"' . preg_quote($key, '/') . '":"([^"]*)"/', $html, $m)) {
            return $m[1] !== '' ? $this->unescapeJsonString($m[1]) : null;
        }
        return null;
    }

    /**
     * Nilai string yang di-regex keluar dari HTML mentah masih membawa
     * escape ala JSON (mis. "\/" untuk garis miring) karena bukan hasil
     * json_decode beneran -- bungkus ulang jadi literal JSON string valid
     * lalu decode supaya "Lemari \/ Storage" jadi "Lemari / Storage".
     */
    protected function unescapeJsonString(string $raw): string
    {
        $decoded = json_decode('"' . $raw . '"');
        return is_string($decoded) ? $decoded : $raw;
    }

    /** Field "location" berbentuk [longitude, latitude] pada halaman Mamikos. */
    protected function extractLatLng(string $html): array
    {
        if (preg_match('/"location":\[([\-0-9.]+),([\-0-9.]+)\]/', $html, $m)) {
            return ['lng' => (float) $m[1], 'lat' => (float) $m[2]];
        }
        return [];
    }

    /**
     * Kumpulkan nama fasilitas dari beberapa kelompok ikon (kamar mandi,
     * kamar, fasilitas bersama, parkir, sorotan utama) -- SENGAJA tidak
     * ambil "fac_near_icon" (poin lokasi sekitar, mis. minimarket/rumah
     * sakit) karena itu bukan fasilitas kos itu sendiri.
     */
    protected function extractFacilities(string $html): array
    {
        $groups = ['fac_bath_icon', 'fac_room_icon', 'fac_share_icon', 'fac_park_icon', 'top_facilities'];
        $names = [];

        foreach ($groups as $group) {
            if (preg_match('/"' . preg_quote($group, '/') . '":\[(.*?)\](?=,")/s', $html, $m)) {
                if (preg_match_all('/"name":"([^"]*)"/', $m[1], $nm)) {
                    foreach ($nm[1] as $n) {
                        $names[$this->unescapeJsonString($n)] = true;
                    }
                }
            }
        }

        return array_keys($names);
    }

    protected function extractOgImage(string $html): ?string
    {
        if (preg_match('/<meta property="og:image" content="([^"]*)"/', $html, $m)) {
            return $m[1];
        }
        return null;
    }

    /** Gender ditentukan dari slug URL (putra/putri/campur) -- lebih andal daripada kode numerik di JSON. */
    protected function extractGenderFromUrl(string $url): ?string
    {
        $slug = mb_strtolower($url);
        if (str_contains($slug, 'putra')) {
            return 'putra';
        }
        if (str_contains($slug, 'putri')) {
            return 'putri';
        }
        if (str_contains($slug, 'campur')) {
            return 'campur';
        }
        return null;
    }
}
