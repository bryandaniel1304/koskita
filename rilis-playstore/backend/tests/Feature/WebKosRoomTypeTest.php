<?php

namespace Tests\Feature;

use App\Models\Kos;
use App\Models\KosRoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebKosRoomTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_kos_detail_page_shows_room_type_breakdown_when_present(): void
    {
        $kos = Kos::factory()->create();
        KosRoomType::factory()->for($kos)->create(['name' => 'Kamar AC', 'price' => 1800000]);

        $response = $this->get("/kos/{$kos->id}");

        $response->assertOk()->assertSee('Kamar AC')->assertSee('Tipe Kamar');
    }

    public function test_kos_detail_page_hides_the_section_when_no_room_types_exist(): void
    {
        $kos = Kos::factory()->create();

        $response = $this->get("/kos/{$kos->id}");

        $response->assertOk()->assertDontSee('Tipe Kamar');
    }
}
