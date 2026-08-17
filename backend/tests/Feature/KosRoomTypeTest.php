<?php

namespace Tests\Feature;

use App\Models\Kos;
use App\Models\KosRoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KosRoomTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function owner(): User
    {
        return User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
    }

    public function test_kos_without_room_types_is_completely_unaffected(): void
    {
        $kos = Kos::factory()->create(['total_rooms' => 5]);
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson("/api/kos/{$kos->id}");

        $response->assertOk();
        $response->assertJsonPath('kos.room_types', []);
        // total_rooms/available_rooms kos TETAP dihitung dari data booking
        // seperti biasa, sama sekali tidak terpengaruh tabel room types.
        $this->assertSame(5, $response->json('kos.available_rooms'));
    }

    public function test_owner_can_add_a_room_type_to_their_own_kos(): void
    {
        $owner = $this->owner();
        $kos = Kos::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($owner, 'sanctum')->postJson("/api/owner/koses/{$kos->id}/room-types", [
            'name' => 'Kamar AC',
            'price' => 1800000,
            'total_rooms' => 3,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('kos_room_types', ['kos_id' => $kos->id, 'name' => 'Kamar AC', 'price' => 1800000]);
    }

    public function test_owner_cannot_add_a_room_type_to_someone_elses_kos(): void
    {
        $owner = $this->owner();
        $otherOwnersKos = Kos::factory()->create();

        $response = $this->actingAs($owner, 'sanctum')->postJson("/api/owner/koses/{$otherOwnersKos->id}/room-types", [
            'name' => 'Kamar AC',
            'price' => 1800000,
            'total_rooms' => 3,
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseCount('kos_room_types', 0);
    }

    public function test_owner_can_update_and_delete_their_room_type(): void
    {
        $owner = $this->owner();
        $kos = Kos::factory()->create(['owner_id' => $owner->id]);
        $roomType = KosRoomType::factory()->for($kos)->create(['name' => 'Kamar AC', 'price' => 1800000]);

        $update = $this->actingAs($owner, 'sanctum')->putJson("/api/owner/koses/{$kos->id}/room-types/{$roomType->id}", [
            'name' => 'Kamar AC Premium',
            'price' => 2000000,
            'total_rooms' => 2,
        ]);
        $update->assertOk();
        $this->assertDatabaseHas('kos_room_types', ['id' => $roomType->id, 'name' => 'Kamar AC Premium', 'price' => 2000000]);

        $delete = $this->actingAs($owner, 'sanctum')->deleteJson("/api/owner/koses/{$kos->id}/room-types/{$roomType->id}");
        $delete->assertOk();
        $this->assertDatabaseMissing('kos_room_types', ['id' => $roomType->id]);
    }

    public function test_kos_show_includes_room_type_breakdown_when_present(): void
    {
        $kos = Kos::factory()->create();
        KosRoomType::factory()->for($kos)->create(['name' => 'Kamar AC', 'price' => 1800000, 'total_rooms' => 3]);
        KosRoomType::factory()->for($kos)->create(['name' => 'Kamar Standar', 'price' => 1200000, 'total_rooms' => 2]);
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson("/api/kos/{$kos->id}");

        $response->assertOk();
        $names = collect($response->json('kos.room_types'))->pluck('name');
        $this->assertTrue($names->contains('Kamar AC'));
        $this->assertTrue($names->contains('Kamar Standar'));
    }

    public function test_deleting_a_kos_cascades_to_its_room_types(): void
    {
        $kos = Kos::factory()->create();
        KosRoomType::factory()->for($kos)->create();

        $kos->delete();

        $this->assertDatabaseCount('kos_room_types', 0);
    }
}
