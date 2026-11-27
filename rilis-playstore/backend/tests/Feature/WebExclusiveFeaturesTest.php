<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Kos;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fitur yang secara struktural cuma masuk akal di web (SEO, sitemap,
 * artikel, halaman lokasi, widget embeddable) -- lihat artifact "KosKita
 * -- Yang Cuma Bisa di Web" untuk konteks lengkapnya.
 */
class WebExclusiveFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_kos_show_page_includes_structured_data_and_og_tags(): void
    {
        $kos = Kos::factory()->create(['name' => 'Kos Uji SEO']);

        $response = $this->get("/kos/{$kos->id}");

        $response->assertOk();
        $response->assertSee('application/ld+json', false);
        $response->assertSee('"@type":"Product"', false);
        $response->assertSee('og:title', false);
        $response->assertSee('twitter:card', false);
    }

    public function test_sitemap_includes_all_published_koses(): void
    {
        Kos::factory()->count(3)->create();

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $xml = simplexml_load_string($response->getContent());
        $this->assertGreaterThanOrEqual(3, count($xml->url));
    }

    public function test_location_landing_page_shows_matching_koses(): void
    {
        Kos::factory()->create(['location' => 'Karawaci', 'name' => 'Kos Karawaci Satu']);
        Kos::factory()->create(['location' => 'BSD', 'name' => 'Kos BSD Satu']);

        $response = $this->get('/kos/lokasi/karawaci');

        $response->assertOk();
        $response->assertSee('Kos Karawaci Satu');
        $response->assertDontSee('Kos BSD Satu');
    }

    public function test_location_landing_page_404s_for_unknown_location(): void
    {
        $response = $this->get('/kos/lokasi/kota-tidak-ada');

        $response->assertStatus(404);
    }

    public function test_widget_search_page_loads_and_links_break_out_of_iframe(): void
    {
        Kos::factory()->count(2)->create();

        $response = $this->get('/widget/search');

        $response->assertOk();
        $response->assertSee('target="_top"', false);
    }

    public function test_published_article_visible_on_public_tips_page(): void
    {
        Article::create([
            'title' => 'Cara Nego Harga Kos',
            'slug' => 'cara-nego-harga-kos',
            'excerpt' => 'Tips singkat nego harga kos.',
            'body' => 'Isi lengkap...',
            'published_at' => now(),
        ]);

        $response = $this->get('/tips');

        $response->assertOk();
        $response->assertSee('Cara Nego Harga Kos');
    }

    public function test_draft_article_not_visible_on_public_tips_page(): void
    {
        Article::create([
            'title' => 'Draf Belum Terbit',
            'slug' => 'draf-belum-terbit',
            'excerpt' => 'Belum siap.',
            'body' => 'Isi...',
            'published_at' => null,
        ]);

        $response = $this->get('/tips');

        $response->assertOk();
        $response->assertDontSee('Draf Belum Terbit');
    }

    public function test_draft_article_404s_on_direct_url(): void
    {
        Article::create([
            'title' => 'Draf Rahasia',
            'slug' => 'draf-rahasia',
            'excerpt' => 'Belum siap.',
            'body' => 'Isi...',
            'published_at' => null,
        ]);

        $response = $this->get('/tips/draf-rahasia');

        $response->assertStatus(404);
    }

    public function test_admin_can_create_and_publish_article(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post('/admin/articles', [
            'title' => 'Checklist Pindahan Kos',
            'excerpt' => 'Panduan sebelum pindah ke kos baru.',
            'body' => 'Isi artikel lengkap.',
            'publish_now' => '1',
        ]);

        $response->assertRedirect(route('admin.articles.index'));
        $this->assertDatabaseHas('articles', ['title' => 'Checklist Pindahan Kos', 'slug' => 'checklist-pindahan-kos']);
        $article = Article::where('slug', 'checklist-pindahan-kos')->firstOrFail();
        $this->assertTrue($article->isPublished());
    }

    public function test_admin_can_save_article_as_draft(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post('/admin/articles', [
            'title' => 'Draf Saja Dulu',
            'excerpt' => 'Belum siap terbit.',
            'body' => 'Isi.',
        ]);

        $article = Article::where('slug', 'draf-saja-dulu')->firstOrFail();
        $this->assertFalse($article->isPublished());
    }

    public function test_duplicate_article_titles_get_unique_slugs(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post('/admin/articles', ['title' => 'Tips Kos', 'excerpt' => 'A', 'body' => 'A']);
        $this->actingAs($admin)->post('/admin/articles', ['title' => 'Tips Kos', 'excerpt' => 'B', 'body' => 'B']);

        $this->assertDatabaseHas('articles', ['slug' => 'tips-kos']);
        $this->assertDatabaseHas('articles', ['slug' => 'tips-kos-2']);
    }
}
