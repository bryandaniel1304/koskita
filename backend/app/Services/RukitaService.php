<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Scraper untuk Rukita (rukita.co). Halaman kategori per-area
 * (rukita.co/kost/{area}) SERVER-RENDERED dengan microdata schema.org
 * (itemProp="name"/"priceRange"/"streetAddress") berisi daftar properti
 * sekaligus -- beda dari Mamikos yang satu URL per kamar, di sini satu
 * request kategori langsung dapat puluhan properti.
 *
 * robots.txt Rukita (`Allow: /`, hanya blokir /search, *?filter,
 * /sewa/kost/*) TIDAK melarang jalur /kost/{area} maupun /place/{slug}
 * yang dipakai di sini.
 */
class RukitaService
{
    protected const USER_AGENT = 'KosKita-Skripsi-Research/1.0 (+riset tugas akhir, bukan bot komersial)';

    // Kosakata fasilitas yang dicari sebagai substring polos di halaman detail
    // (Rukita tidak menandai fasilitas dengan microdata terpisah seperti
    // Mamikos, jadi dicek keberadaan kata kuncinya saja).
    protected const FACILITY_KEYWORDS = [
        'AC', 'Wifi', 'Kamar Mandi Dalam', 'Kolam Renang', 'Smart Lock',
        'Dapur Bersama', 'Laundry', 'Parkir', 'Lift', 'Gym',
    ];

    /**
     * Ambil daftar properti dari halaman kategori satu area.
     *
     * @return array<int, array{name:string,price_text:string,price_monthly:?int,address:?string,detail_path:string}>
     */
    public function fetchCategoryListing(string $areaSlug): array
    {
        $url = "https://www.rukita.co/kost/{$areaSlug}";
        $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])->timeout(15)->get($url);

        if (!$response->successful()) {
            Log::warning('RukitaService::fetchCategoryListing gagal', ['url' => $url, 'status' => $response->status()]);
            return [];
        }

        $html = $response->body();

        preg_match_all('/itemProp="name">([^<]+)/', $html, $names);
        preg_match_all('/itemProp="priceRange">([^<]+)/', $html, $prices);
        preg_match_all('/itemProp="streetAddress">([^<]+)/', $html, $addrs);
        preg_match_all('/href="(\/place\/[a-zA-Z0-9_-]+)"/', $html, $hrefs);

        // 3 nama pertama selalu breadcrumb (Home/Kota/Area), bukan properti --
        // lihat catatan investigasi: names[3..] baru properti sungguhan.
        $propertyNames = array_slice($names[1], 3);
        $propertyPrices = $prices[1];
        $propertyAddrs = $addrs[1];
        $uniqueHrefs = array_values(array_unique($hrefs[1]));

        $results = [];
        $count = min(count($propertyNames), count($propertyPrices), count($propertyAddrs), count($uniqueHrefs));

        for ($i = 0; $i < $count; $i++) {
            $results[] = [
                'name' => trim(html_entity_decode($propertyNames[$i])),
                'price_text' => trim($propertyPrices[$i]),
                'price_monthly' => $this->parseLowestPrice($propertyPrices[$i]),
                'address' => trim($propertyAddrs[$i]),
                'detail_path' => $uniqueHrefs[$i],
            ];
        }

        return $results;
    }

    /**
     * Lengkapi satu properti dengan foto, koordinat, dan fasilitas dari
     * halaman detailnya sendiri.
     */
    public function enrichFromDetailPage(array $item): array
    {
        $url = 'https://www.rukita.co' . $item['detail_path'];
        $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])->timeout(15)->get($url);

        if (!$response->successful()) {
            Log::warning('RukitaService::enrichFromDetailPage gagal', ['url' => $url, 'status' => $response->status()]);
            $item['image_url'] = null;
            $item['lat'] = null;
            $item['lng'] = null;
            $item['facilities'] = [];
            $item['gender'] = 'campur';
            $item['source_url'] = $url;
            return $item;
        }

        $html = $response->body();

        if (preg_match('/<meta property="og:image" content="([^"]*)"/', $html, $m)) {
            $item['image_url'] = $m[1];
        } else {
            $item['image_url'] = null;
        }

        // Koordinat muncul sebagai pasangan "-6.xxxxx" / "106.xxxxx" polos di
        // halaman (bukan lewat microdata terpisah) -- ambil pasangan pertama
        // yang berdekatan sebagai lokasi properti.
        if (preg_match('/(-6\.\d{4,8})/', $html, $lat) && preg_match('/(106\.\d{4,8})/', $html, $lng)) {
            $item['lat'] = (float) $lat[1];
            $item['lng'] = (float) $lng[1];
        } else {
            $item['lat'] = null;
            $item['lng'] = null;
        }

        $facilities = [];
        foreach (self::FACILITY_KEYWORDS as $keyword) {
            if (stripos($html, $keyword) !== false) {
                $facilities[] = $keyword;
            }
        }
        $item['facilities'] = $facilities;

        // Rukita adalah brand coliving yang mayoritas unitnya campur/tidak
        // memisahkan gender secara eksplisit -- default 'campur' kecuali
        // halaman menyebut "khusus pria/wanita" secara jelas.
        $item['gender'] = preg_match('/khusus (pria|wanita)/i', $html, $g)
            ? (mb_strtolower($g[1]) === 'pria' ? 'putra' : 'putri')
            : 'campur';

        $item['source_url'] = $url;

        return $item;
    }

    /** "Rp 2.768.000 - Rp 3.018.000" atau "Rp 1.250.000" -> ambil angka terendah. */
    protected function parseLowestPrice(string $priceText): ?int
    {
        if (preg_match('/Rp\s?([\d.]+)/', $priceText, $m)) {
            return (int) str_replace('.', '', $m[1]);
        }
        return null;
    }
}
