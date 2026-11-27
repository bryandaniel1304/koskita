<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileBroadcastingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // phpunit.xml memaksa BROADCAST_CONNECTION=null untuk seluruh suite
        // (supaya tidak ada percobaan broadcast sungguhan di test lain) --
        // konsekuensinya Broadcast::channel(...) di routes/channels.php
        // ter-daftar ke instance NullBroadcaster (default saat boot), BUKAN
        // ke driver 'reverb' yang baru dibuat kalau kita ganti default
        // belakangan -- tiap driver punya registry channel sendiri-sendiri,
        // tidak ada yang dibagi. Testing otorisasi channel yang sesungguhnya
        // (bukan sekadar "endpoint dipanggil") butuh driver asli DAN definisi
        // channel-nya didaftarkan ulang ke driver itu -- HMAC signing lokal
        // saja, TIDAK ada panggilan jaringan ke server Reverb sungguhan.
        config(['broadcasting.default' => 'reverb']);
        require base_path('routes/channels.php');
    }

    public function test_broadcasting_config_is_public_and_never_leaks_the_app_secret(): void
    {
        $response = $this->getJson('/api/broadcasting/config');

        $response->assertOk();
        $response->assertJsonStructure(['key', 'port', 'scheme']);
        $response->assertJsonMissingPath('secret');
    }

    public function test_guest_cannot_authorize_a_private_channel(): void
    {
        $response = $this->postJson('/api/broadcasting/auth', [
            'channel_name' => 'private-App.Models.User.1',
            'socket_id' => '123.456',
        ]);

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_authorize_their_own_private_channel(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/broadcasting/auth', [
            'channel_name' => 'private-App.Models.User.' . $user->id,
            'socket_id' => '123.456',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['auth']);
    }

    public function test_authenticated_user_cannot_authorize_someone_elses_private_channel(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/broadcasting/auth', [
            'channel_name' => 'private-App.Models.User.' . $other->id,
            'socket_id' => '123.456',
        ]);

        $response->assertStatus(403);
    }
}
