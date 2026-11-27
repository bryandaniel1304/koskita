<?php

namespace Tests\Feature;

use App\Models\Kos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebCompareTest extends TestCase
{
    use RefreshDatabase;

    public function test_comparing_two_or_more_kos_shows_the_table(): void
    {
        $a = Kos::factory()->create(['name' => 'Kos Alpha']);
        $b = Kos::factory()->create(['name' => 'Kos Beta']);

        $response = $this->get("/bandingkan?ids={$a->id},{$b->id}");

        $response->assertOk()->assertSee('Kos Alpha')->assertSee('Kos Beta');
    }

    public function test_comparing_with_fewer_than_two_ids_redirects_back(): void
    {
        $a = Kos::factory()->create();

        $response = $this->get("/bandingkan?ids={$a->id}");

        $response->assertRedirect(route('web.kos.index'));
        $response->assertSessionHasErrors('compare');
    }

    public function test_comparing_more_than_three_kos_shows_them_all(): void
    {
        // Tidak ada batas bisnis lagi -- tabelnya sudah bisa discroll ke
        // samping (.table-responsive), jadi 10 kos sekaligus pun harus tetap
        // muncul semua, bukan cuma 3 yang pertama.
        $ids = Kos::factory()->count(10)->create()->pluck('id')->implode(',');

        $response = $this->get("/bandingkan?ids={$ids}");

        $response->assertOk();
        $response->assertViewHas('koses', function ($koses) {
            return $koses->count() === 10;
        });
    }

    public function test_comparing_is_capped_at_fifty_kos_as_an_anti_abuse_ceiling(): void
    {
        $ids = Kos::factory()->count(55)->create()->pluck('id')->implode(',');

        $response = $this->get("/bandingkan?ids={$ids}");

        $response->assertOk();
        $response->assertViewHas('koses', function ($koses) {
            return $koses->count() === 50;
        });
    }
}
