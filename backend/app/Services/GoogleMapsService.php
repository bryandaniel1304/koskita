<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client tipis untuk Google Maps Platform (Places API New). Dipakai untuk
 * riset data akomodasi (kos, kost, guesthouse, hotel) di sekitar wilayah
 * studi kasus skripsi (Karawaci/BSD/Serpong) lewat `php artisan
 * places:search-lodging` -- BUKAN dipanggil dari alur aplikasi produksi.
 *
 * Catatan lisensi: hasil Places API tunduk pada Google Maps Platform Terms
 * of Service -- boleh di-cache sementara untuk tampilan, tapi tidak boleh
 * dijadikan database permanen redistributable. Karena itu command yang
 * memakai service ini hanya menulis hasil sebagai berkas JSON referensi
 * untuk ditinjau manual, bukan auto-import ke tabel `koses`.
 */
class GoogleMapsService
{
    protected ?string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.google_maps.key');
    }

    public function configured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Text Search (Places API New) dengan location bias radius, mengambil
     * sampai $maxPages halaman (tiap halaman maksimal 20 hasil) supaya
     * biaya & waktu tetap terkendali untuk kebutuhan riset skala skripsi.
     *
     * @return array<int, array{place_id:?string,name:?string,address:?string,lat:?float,lng:?float,types:array,rating:?float,rating_count:?int}>
     */
    public function searchText(string $query, float $lat, float $lng, float $radiusMeters = 4000, int $maxPages = 2): array
    {
        if (!$this->configured()) {
            throw new \RuntimeException('GOOGLE_MAPS_API_KEY belum diisi di .env.');
        }

        $results = [];
        $pageToken = null;

        for ($page = 0; $page < $maxPages; $page++) {
            $body = [
                'textQuery' => $query,
                'maxResultCount' => 20,
                'locationBias' => [
                    'circle' => [
                        'center' => ['latitude' => $lat, 'longitude' => $lng],
                        'radius' => $radiusMeters,
                    ],
                ],
            ];
            if ($pageToken) {
                $body['pageToken'] = $pageToken;
            }

            $response = Http::withHeaders([
                'X-Goog-Api-Key' => $this->apiKey,
                'X-Goog-FieldMask' => 'places.id,places.displayName,places.formattedAddress,'
                    . 'places.location,places.types,places.rating,places.userRatingCount,nextPageToken',
            ])->timeout(10)->post('https://places.googleapis.com/v1/places:searchText', $body);

            if (!$response->successful()) {
                Log::warning('GoogleMapsService::searchText gagal', [
                    'query' => $query,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                break;
            }

            $data = $response->json();
            foreach ($data['places'] ?? [] as $place) {
                $results[] = [
                    'place_id' => $place['id'] ?? null,
                    'name' => $place['displayName']['text'] ?? null,
                    'address' => $place['formattedAddress'] ?? null,
                    'lat' => $place['location']['latitude'] ?? null,
                    'lng' => $place['location']['longitude'] ?? null,
                    'types' => $place['types'] ?? [],
                    'rating' => $place['rating'] ?? null,
                    'rating_count' => $place['userRatingCount'] ?? null,
                ];
            }

            $pageToken = $data['nextPageToken'] ?? null;
            if (!$pageToken) {
                break;
            }

            // Google mensyaratkan jeda singkat sebelum page token berikutnya valid dipakai.
            sleep(2);
        }

        return $results;
    }

    /**
     * Place Details (field mask "photos" saja, hemat kuota) -- ambil nama
     * resource foto pertama untuk dipakai downloadPhoto(). Return null
     * kalau tempat ini tidak punya foto di Google.
     */
    public function firstPhotoName(string $placeId): ?string
    {
        if (!$this->configured()) {
            throw new \RuntimeException('GOOGLE_MAPS_API_KEY belum diisi di .env.');
        }

        $response = Http::withHeaders([
            'X-Goog-Api-Key' => $this->apiKey,
            'X-Goog-FieldMask' => 'photos',
        ])->timeout(10)->get("https://places.googleapis.com/v1/places/{$placeId}");

        if (!$response->successful()) {
            return null;
        }

        return $response->json('photos.0.name');
    }

    /**
     * Unduh byte foto asli dari Places Photo Media endpoint. Wajib
     * dilampiri atribusi ("Photos by Google" / authorAttributions) saat
     * ditampilkan di aplikasi sesuai Google Maps Platform ToS -- lihat
     * catatan pemakaian di ImportRealKoses.
     */
    public function downloadPhoto(string $photoResourceName, int $maxWidthPx = 800): ?string
    {
        if (!$this->configured()) {
            throw new \RuntimeException('GOOGLE_MAPS_API_KEY belum diisi di .env.');
        }

        $response = Http::withHeaders(['X-Goog-Api-Key' => $this->apiKey])
            ->timeout(10)
            ->get("https://places.googleapis.com/v1/{$photoResourceName}/media", [
                'maxWidthPx' => $maxWidthPx,
                'skipHttpRedirect' => 'false',
            ]);

        if (!$response->successful()) {
            return null;
        }

        return $response->body();
    }
}
