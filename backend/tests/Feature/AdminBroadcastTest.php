<?php

namespace Tests\Feature;

use App\Models\AdminBroadcast;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test untuk fitur pengumuman admin -- dibuat lewat panel admin, muncul
 * menyatu di feed notifikasi in-app (GET /api/notifications) penyewa/pemilik
 * sesuai target_role.
 */
class AdminBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_send_broadcast(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post('/admin/broadcasts', [
            'title' => 'Pemeliharaan Server',
            'message' => 'Server akan maintenance jam 2 pagi.',
            'target_role' => '',
        ]);

        $response->assertRedirect(route('admin.broadcasts.index'));
        $this->assertDatabaseHas('admin_broadcasts', [
            'title' => 'Pemeliharaan Server',
            'target_role' => null,
        ]);
    }

    public function test_broadcast_appears_in_tenant_notifications_when_targeted_to_all(): void
    {
        AdminBroadcast::create(['title' => 'Info Semua', 'message' => 'Untuk semua pengguna.', 'target_role' => null]);
        $tenant = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($tenant, 'sanctum')->getJson('/api/notifications');

        $response->assertOk();
        $response->assertJsonFragment(['type' => 'broadcast', 'title' => 'Info Semua']);
    }

    public function test_broadcast_targeted_to_owner_does_not_appear_for_tenant(): void
    {
        AdminBroadcast::create(['title' => 'Khusus Pemilik', 'message' => 'Info buat pemilik saja.', 'target_role' => 'owner']);
        $tenant = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($tenant, 'sanctum')->getJson('/api/notifications');

        $response->assertOk();
        $response->assertJsonMissing(['title' => 'Khusus Pemilik']);
    }

    public function test_broadcast_targeted_to_owner_appears_for_owner(): void
    {
        AdminBroadcast::create(['title' => 'Khusus Pemilik', 'message' => 'Info buat pemilik saja.', 'target_role' => 'owner']);
        $owner = User::factory()->create(['role' => 'owner']);

        $response = $this->actingAs($owner, 'sanctum')->getJson('/api/notifications');

        $response->assertOk();
        $response->assertJsonFragment(['type' => 'broadcast', 'title' => 'Khusus Pemilik']);
    }
}
