<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Parameter WebSocket yang diterima client harus mengikuti driver yang aktif:
 * Reverb untuk lokal/VPS, Pusher untuk shared hosting (cPanel). Konfigurasi
 * diset eksplisit di sini supaya tes tidak bergantung pada isi .env.
 */
class RealtimeConfigTest extends TestCase
{
    use RefreshDatabase;

    protected function useReverb(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'reverb-public-key',
            'broadcasting.connections.reverb.secret' => 'reverb-secret',
            'broadcasting.connections.reverb.options.host' => 'localhost',
            'broadcasting.connections.reverb.options.port' => 8080,
            'broadcasting.connections.reverb.options.useTLS' => false,
        ]);
    }

    protected function usePusher(): void
    {
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher.key' => 'pusher-public-key',
            'broadcasting.connections.pusher.secret' => 'pusher-secret',
            'broadcasting.connections.pusher.app_id' => '123',
            'broadcasting.connections.pusher.options.cluster' => 'ap1',
        ]);
    }

    public function test_reverb_config_leaves_the_host_to_the_app(): void
    {
        $this->useReverb();

        $this->getJson('/api/broadcasting/config')
            ->assertOk()
            ->assertExactJson(['key' => 'reverb-public-key', 'port' => 8080, 'scheme' => 'ws']);
    }

    public function test_pusher_config_points_the_app_to_the_pusher_websocket_host(): void
    {
        $this->usePusher();

        $this->getJson('/api/broadcasting/config')
            ->assertOk()
            ->assertExactJson([
                'key' => 'pusher-public-key',
                'host' => 'ws-ap1.pusher.com',
                'port' => 443,
                'scheme' => 'wss',
            ]);
    }

    public function test_neither_driver_leaks_its_secret(): void
    {
        foreach (['useReverb', 'usePusher'] as $driver) {
            $this->{$driver}();
            $body = $this->getJson('/api/broadcasting/config')->getContent();
            $this->assertStringNotContainsString('secret', $body, $driver);
        }
    }

    public function test_web_chat_page_connects_with_pusher_when_pusher_is_active(): void
    {
        $this->usePusher();
        [$tenant, $owner] = $this->conversation();

        $this->actingAs($tenant)->get("/pesan/{$owner->id}")
            ->assertOk()
            ->assertSee('"driver":"pusher"', false)
            ->assertSee('"cluster":"ap1"', false)
            ->assertDontSee('pusher-secret');
    }

    public function test_web_chat_page_still_connects_with_reverb_by_default(): void
    {
        $this->useReverb();
        [$tenant, $owner] = $this->conversation();

        $this->actingAs($tenant)->get("/pesan/{$owner->id}")
            ->assertOk()
            ->assertSee('"driver":"reverb"', false)
            ->assertSee('"host":"localhost"', false)
            ->assertDontSee('reverb-secret');
    }

    /** @return array{0: User, 1: User} */
    protected function conversation(): array
    {
        $tenant = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);
        $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
        Message::create(['sender_id' => $tenant->id, 'receiver_id' => $owner->id, 'body' => 'Halo, masih ada kamar?']);

        return [$tenant, $owner];
    }
}
