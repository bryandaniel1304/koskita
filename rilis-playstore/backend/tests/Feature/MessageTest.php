<?php

namespace Tests\Feature;

use App\Models\Kos;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use RefreshDatabase;

    protected function tenant(): User
    {
        return User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);
    }

    protected function owner(): User
    {
        return User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
    }

    public function test_tenant_can_message_owner(): void
    {
        $tenant = $this->tenant();
        $owner = $this->owner();
        $kos = Kos::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($tenant, 'sanctum')->postJson('/api/messages', [
            'receiver_id' => $owner->id,
            'kos_id' => $kos->id,
            'body' => 'Kamarnya masih kosong nggak ya?',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('messages', [
            'sender_id' => $tenant->id,
            'receiver_id' => $owner->id,
            'kos_id' => $kos->id,
        ]);
    }

    public function test_cannot_message_same_role_user(): void
    {
        $tenantA = $this->tenant();
        $tenantB = $this->tenant();

        $response = $this->actingAs($tenantA, 'sanctum')->postJson('/api/messages', [
            'receiver_id' => $tenantB->id,
            'body' => 'Halo',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_cannot_message_self(): void
    {
        $tenant = $this->tenant();

        $response = $this->actingAs($tenant, 'sanctum')->postJson('/api/messages', [
            'receiver_id' => $tenant->id,
            'body' => 'Halo',
        ]);

        $response->assertStatus(422);
    }

    public function test_thread_marks_incoming_messages_as_read(): void
    {
        $tenant = $this->tenant();
        $owner = $this->owner();

        Message::create(['sender_id' => $tenant->id, 'receiver_id' => $owner->id, 'body' => 'Halo pak']);
        Message::create(['sender_id' => $tenant->id, 'receiver_id' => $owner->id, 'body' => 'Ada info lebih?']);

        $response = $this->actingAs($owner, 'sanctum')->getJson("/api/messages/thread/{$tenant->id}");

        $response->assertOk();
        $response->assertJsonCount(2, 'messages');
        $this->assertSame(0, Message::whereNull('read_at')->count());
    }

    public function test_conversations_list_shows_last_message_and_unread_count(): void
    {
        $tenant = $this->tenant();
        $owner = $this->owner();

        Message::create(['sender_id' => $tenant->id, 'receiver_id' => $owner->id, 'body' => 'Pesan pertama']);
        Message::create(['sender_id' => $tenant->id, 'receiver_id' => $owner->id, 'body' => 'Pesan kedua']);

        $response = $this->actingAs($owner, 'sanctum')->getJson('/api/messages/conversations');

        $response->assertOk();
        $data = $response->json('conversations');
        $this->assertCount(1, $data);
        $this->assertSame('Pesan kedua', $data[0]['last_message']['body']);
        $this->assertSame(2, $data[0]['unread_count']);
    }

    public function test_unread_count_endpoint(): void
    {
        $tenant = $this->tenant();
        $owner = $this->owner();

        Message::create(['sender_id' => $tenant->id, 'receiver_id' => $owner->id, 'body' => 'Halo']);

        $response = $this->actingAs($owner, 'sanctum')->getJson('/api/messages/unread-count');

        $response->assertOk();
        $response->assertJson(['unread_count' => 1]);
    }
}
