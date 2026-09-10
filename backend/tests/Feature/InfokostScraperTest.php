<?php

namespace Tests\Feature;

use App\Services\InfokostService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Uji parser Infokost terhadap POTONGAN HTML ASLI (tests/Fixtures/
 * infokost-area-page.html, dua kartu listing yang diambil apa adanya dari
 * halaman /kost/karawaci-tangerang).
 *
 * Gunanya: scraping dijalankan ulang berkali-kali selama penelitian, dan
 * kalau Infokost mengubah markup-nya, parser akan diam-diam mengembalikan
 * nol listing tanpa error. Test ini bikin kerusakan itu ketahuan langsung.
 *
 * Http::fake() dipakai supaya test TIDAK menembak situs mereka tiap kali
 * suite dijalankan.
 */
class InfokostScraperTest extends TestCase
{
    private function fakeAreaPage(): void
    {
        Http::fake([
            'infokost.id/*' => Http::response(
                file_get_contents(base_path('tests/Fixtures/infokost-area-page.html')),
                200
            ),
        ]);
    }

    public function test_parses_all_listing_attributes_from_real_markup(): void
    {
        $this->fakeAreaPage();

        $items = app(InfokostService::class)->fetchAreaPage('karawaci-tangerang');

        $this->assertCount(2, $items);

        $first = $items[0];
        $this->assertSame('Rukita Edelweis Lincoln Karawaci', $first['name']);
        $this->assertSame('Compact Single A', $first['room_type']);
        $this->assertSame(1668000, $first['price_monthly']);
        $this->assertSame('campur', $first['gender']);
        $this->assertSame('Curug, Kabupaten Tangerang', $first['address']);
        $this->assertSame(13, $first['distance_minutes']);
        $this->assertStringContainsString('Universitas Pelita Harapan', $first['distance_text']);
        $this->assertStringStartsWith('https://', $first['image_url']);
        $this->assertSame(
            'https://infokost.id/listings/rukita-edelweis-lincoln-karawaci-compact-single-a-4463',
            $first['source_url']
        );
    }

    public function test_address_is_cleaned_of_react_hydration_comments(): void
    {
        $this->fakeAreaPage();

        $items = app(InfokostService::class)->fetchAreaPage('karawaci-tangerang');

        // Markup aslinya "Curug<!-- -->, <!-- -->Kabupaten Tangerang" --
        // komentar hidrasi React tidak boleh ikut bocor ke data penelitian.
        $this->assertStringNotContainsString('<!--', $items[0]['address']);
    }

    public function test_detects_highest_pagination_number(): void
    {
        $this->fakeAreaPage();

        // Fixture memuat tautan ke halaman 2 dan 7 -- yang dipakai harus
        // yang TERBESAR, bukan yang pertama ditemukan.
        $this->assertSame(7, app(InfokostService::class)->detectTotalPages('karawaci-tangerang'));
    }

    public function test_returns_empty_array_when_site_is_unreachable(): void
    {
        Http::fake(['infokost.id/*' => Http::response('', 503)]);

        // Gagal jaringan harus jadi array kosong (command menandainya
        // sebagai gagal), bukan exception yang menghentikan scraping area lain.
        $this->assertSame([], app(InfokostService::class)->fetchAreaPage('karawaci-tangerang'));
        $this->assertSame(1, app(InfokostService::class)->detectTotalPages('karawaci-tangerang'));
    }
}
