<?php

namespace Tests\Feature;

use App\Models\Kos;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebMessageTest extends TestCase
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

    public function test_tenant_can_send_message_to_owner_via_web(): void
    {
        $tenant = $this->tenant();
        $owner = $this->owner();
        $kos = Kos::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($tenant)->post('/pesan', [
            'receiver_id' => $owner->id,
            'kos_id' => $kos->id,
            'body' => 'Masih kosong kamarnya?',
        ]);

        $response->assertRedirect(route('web.messages.thread', $owner->id));
        $this->assertDatabaseHas('messages', [
            'sender_id' => $tenant->id,
            'receiver_id' => $owner->id,
        ]);
    }

    public function test_web_thread_page_shows_messages_and_marks_read(): void
    {
        $tenant = $this->tenant();
        $owner = $this->owner();

        Message::create(['sender_id' => $tenant->id, 'receiver_id' => $owner->id, 'body' => 'Halo pak pemilik']);

        $response = $this->actingAs($owner)->get("/pesan/{$tenant->id}");

        $response->assertOk();
        $response->assertSee('Halo pak pemilik');
        $this->assertSame(0, Message::whereNull('read_at')->count());
    }

    public function test_web_conversations_index_shows_unread_badge(): void
    {
        $tenant = $this->tenant();
        $owner = $this->owner();

        Message::create(['sender_id' => $tenant->id, 'receiver_id' => $owner->id, 'body' => 'Halo']);

        $response = $this->actingAs($owner)->get('/pesan');

        $response->assertOk();
        $response->assertSee($tenant->name);
    }

    public function test_web_cannot_message_same_role(): void
    {
        $tenantA = $this->tenant();
        $tenantB = $this->tenant();

        $response = $this->actingAs($tenantA)->post('/pesan', [
            'receiver_id' => $tenantB->id,
            'body' => 'Halo',
        ]);

        $response->assertSessionHasErrors('message');
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_guest_cannot_access_messages(): void
    {
        $response = $this->get('/pesan');

        $response->assertRedirect(route('web.login'));
    }
}
