<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Scraper untuk Infokost (infokost.id). Halaman area /kost/{slug} (dan
 * paginasinya /kost/{slug}/{n}) SERVER-RENDERED dan satu kartu listing
 * sudah memuat SEMUA yang dibutuhkan -- nama, tipe kamar, gender, lokasi,
 * jarak ke kampus, harga, dan foto. Jadi tidak perlu membuka halaman
 * detail satu per satu seperti scraper Mamikos: satu request = ~21
 * listing, jauh lebih ringan untuk server mereka sekaligus lebih cepat.
 *
 * robots.txt Infokost (`Allow: /`, satu-satunya larangan `Disallow: /cari?`)
 * TIDAK melarang jalur /kost/{slug} yang dipakai di sini -- justru jalur
 * itu yang mereka daftarkan sendiri di sitemap /cari-kost/sitemap_index.xml.
 *
 * Nilai khas sumber ini untuk skripsi: teks "N menit ke {kampus}" yang
 * tidak disediakan Mamikos/Rukita, padahal jarak-ke-kampus adalah salah
 * satu atribut pada model rekomendasi (lihat ContentBasedFilter).
 */
class InfokostService
{
    protected const USER_AGENT = 'KosKita-Skripsi-Research/1.0 (+riset tugas akhir, bukan bot komersial)';

    protected const BASE_URL = 'https://infokost.id';

    /**
     * Ambil satu halaman daftar kos untuk satu slug area.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchAreaPage(string $areaSlug, int $page = 1): array
    {
        $url = self::BASE_URL . "/kost/{$areaSlug}" . ($page > 1 ? "/{$page}" : '');

        $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])->timeout(30)->get($url);

        if (!$response->successful()) {
            Log::warning('InfokostService::fetchAreaPage gagal', ['url' => $url, 'status' => $response->status()]);
            return [];
        }

        return $this->parseCards($response->body(), $url);
    }

    /**
     * Berapa halaman yang tersedia untuk satu area -- dibaca dari tautan
     * paginasi di halaman pertama, supaya command tidak menembak halaman
     * kosong yang tidak ada isinya.
     */
    public function detectTotalPages(string $areaSlug): int
    {
        $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])->timeout(30)
            ->get(self::BASE_URL . "/kost/{$areaSlug}");

        if (!$response->successful()) {
            return 1;
        }

        preg_match_all('#href="/kost/' . preg_quote($areaSlug, '#') . '/(\d+)"#', $response->body(), $m);

        return empty($m[1]) ? 1 : max(array_map('intval', $m[1]));
    }

    /**
     * Pecah HTML jadi kartu-kartu listing lalu ambil atributnya.
     *
     * Dipisah per tautan "/listings/" karena tiap kartu adalah satu <a>
     * -- pendekatan ini tahan terhadap perubahan nama kelas Tailwind yang
     * di-generate build Next.js mereka (kelasnya berubah tiap deploy,
     * strukturnya tidak).
     */
    protected function parseCards(string $html, string $sourceUrl): array
    {
        $parts = preg_split('#href="/listings/#', $html);
        array_shift($parts); // potongan sebelum kartu pertama = header halaman

        $results = [];

        foreach ($parts as $part) {
            // Batasi ke isi satu kartu saja supaya atribut kartu berikutnya
            // tidak ikut terbaca kalau salah satu pola tidak ketemu.
            $card = substr($part, 0, strpos($part, '</a>') ?: 4000);

            if (!preg_match('#^([a-z0-9-]+)"#', $card, $slugMatch)) {
                continue;
            }
            $slug = $slugMatch[1];

            $name = $this->firstMatch('#<h3[^>]*>([^<]+)</h3>#', $card);
            if (!$name) {
                continue; // bukan kartu listing (mis. tautan navigasi)
            }

            $priceText = $this->firstMatch('#(Rp[\d.]+)#', $card);

            $results[] = [
                'name' => $this->clean($name),
                'room_type' => $this->clean($this->firstMatch('#</h3><p[^>]*>([^<]+)</p>#', $card) ?? ''),
                'price_text' => $priceText,
                'price_monthly' => $priceText ? (int) str_replace('.', '', substr($priceText, 2)) : null,
                'gender' => $this->parseGender($card),
                'address' => $this->parseAddress($card),
                'distance_text' => $this->clean($this->firstMatch('#<p[^>]*>(\d+ menit ke [^<]+)</p>#', $card) ?? ''),
                'distance_minutes' => (int) ($this->firstMatch('#(\d+) menit ke#', $card) ?? 0) ?: null,
                // Koordinat & daftar fasilitas tidak tersedia di kartu; keduanya
                // ada di halaman detail, TIDAK diambil di sini supaya jumlah
                // request tetap 1 per 21 listing (lihat catatan kelas).
                'lat' => null,
                'lng' => null,
                'facilities' => [],
                'image_url' => $this->firstMatch('#src="(https://[^"]+\.(?:jpg|jpeg|png|webp))"#', $card),
                'source_url' => self::BASE_URL . '/listings/' . $slug,
                'listing_page_url' => $sourceUrl,
            ];
        }

        return $results;
    }


    /**
     * Lengkapi satu listing dari halaman detailnya: koordinat, alamat jalan,
     * fasilitas, dan PERATURAN kos.
     *
     * Kartu di halaman area sengaja tidak memuat ini (lihat catatan kelas),
     * padahal koordinat wajib untuk menghitung distance_to_campus dan
     * fasilitas wajib untuk pembobotan Content-Based. Tanpa langkah ini,
     * seluruh listing Infokost akan masuk database dengan jarak 0 km dan
     * tanpa fasilitas -- yaitu persis "nilai karangan" yang dilarang prinsip
     * places:import-koses.
     *
     * Datanya diambil dari JSON Next.js yang tertanam di halaman (bukan dari
     * markup visual), sehingga tidak ikut berubah saat mereka mengganti
     * tata letak. Tanda kutipnya ter-escape di dalam HTML, jadi dibuka dulu
     * sebelum dicocokkan.
     *
     * Peraturan kos adalah bonus yang tidak disediakan Mamikos maupun
     * Rukita -- lihat catatan "rules dibiarkan kosong" di ImportRealKoses.
     */
    public function enrichFromDetailPage(array $item): array
    {
        // Satu gangguan jaringan di tengah ratusan request TIDAK boleh
        // menghentikan seluruh proses -- sebelumnya exception koneksi
        // mematikan command di listing ke-128 dan sisanya tidak tersentuh.
        try {
            $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
                ->timeout(30)
                ->retry(2, 2000, throw: false)
                ->get($item['source_url']);
        } catch (\Throwable $e) {
            Log::warning('InfokostService::enrichFromDetailPage error jaringan', [
                'url' => $item['source_url'],
                'error' => $e->getMessage(),
            ]);
            return $item;
        }

        if (!$response->successful()) {
            Log::warning('InfokostService::enrichFromDetailPage gagal', [
                'url' => $item['source_url'],
                'status' => $response->status(),
            ]);
            return $item;
        }

        $plain = str_replace('\\"', '"', $response->body());

        // Alamat & koordinat dicocokkan BERSAMAAN dalam satu pola supaya
        // dijamin berasal dari objek properti yang sama -- halaman detail
        // juga memuat properti lain (rekomendasi "kos serupa"), jadi
        // mencocokkan keduanya terpisah berisiko mengambil alamat properti
        // A dengan koordinat properti B.
        if (preg_match('/"address":"([^"]+)","coordinate":\{"latitude":"(-?[\d.]+)","longitude":"([\d.]+)"\}/', $plain, $m)) {
            $item['street_address'] = $this->clean($m[1]);
            $item['lat'] = (float) $m[2];
            $item['lng'] = (float) $m[3];
        }

        preg_match_all(
            '/"attrGroupName":"([^"]+)","attrId":"[A-Z_0-9]+","attrName":"([^"]+)"/',
            $plain,
            $attrs,
            PREG_SET_ORDER
        );

        $facilities = [];
        $rules = [];
        foreach ($attrs as $attr) {
            $group = $this->clean($attr[1]);
            $name = $this->clean($attr[2]);

            // Grup "House Rules" berisi peraturan (boleh bawa hewan, aturan
            // menginap, dsb), bukan fasilitas fisik -- dipisah supaya tidak
            // tercampur jadi "fasilitas" yang menyesatkan di detail kos.
            if (stripos($group, 'House Rules') !== false) {
                $rules[$name] = true;
            } else {
                $facilities[$name] = true;
            }
        }

        if ($facilities) {
            $item['facilities'] = array_keys($facilities);
        }
        if ($rules) {
            $item['rules'] = array_keys($rules);
        }

        return $item;
    }

    /** Badge gender ("Campur"/"Putra"/"Putri") dinormalkan ke kosakata KosKita. */
    protected function parseGender(string $card): string
    {
        foreach (['Putra' => 'putra', 'Putri' => 'putri', 'Campur' => 'campur'] as $label => $value) {
            if (str_contains($card, ">{$label}</div>")) {
                return $value;
            }
        }
        return 'campur';
    }

    /** "Kelapa Dua<!-- -->, <!-- -->Kabupaten Tangerang" -> "Kelapa Dua, Kabupaten Tangerang". */
    protected function parseAddress(string $card): ?string
    {
        $raw = $this->firstMatch('#<span class="line-clamp-1">((?:[^<]|<!-- -->)+)</span>#', $card);

        return $raw ? $this->clean($raw) : null;
    }

    protected function firstMatch(string $pattern, string $subject): ?string
    {
        return preg_match($pattern, $subject, $m) ? $m[1] : null;
    }

    /** Buang komentar hidrasi React (<!-- -->) & entitas HTML. */
    protected function clean(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(str_replace('<!-- -->', '', $value))));
    }
}
