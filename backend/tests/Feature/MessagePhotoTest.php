<?php

namespace Tests\Feature;

use App\Events\MessageSent;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MessagePhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_can_send_a_photo_only_message_without_text(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create(['role' => 'owner']);
        $tenant = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($tenant, 'sanctum')->postJson('/api/messages', [
            'receiver_id' => $owner->id,
            'photo' => UploadedFile::fake()->create('kamar.jpg', 100, 'image/jpeg'),
        ]);

        $response->assertCreated();
        $message = Message::first();
        $this->assertNotNull($message->photo_path);
        Storage::disk('public')->assertExists($message->photo_path);
        $this->assertSame('', $message->body);
    }

    public function test_api_rejects_completely_empty_message(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $tenant = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($tenant, 'sanctum')->postJson('/api/messages', [
            'receiver_id' => $owner->id,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_web_can_send_a_photo_with_text(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create(['role' => 'owner']);
        $tenant = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);

        $response = $this->actingAs($tenant)->post('/pesan', [
            'receiver_id' => $owner->id,
            'body' => 'Ini kamarnya ya',
            'photo' => UploadedFile::fake()->create('kamar.jpg', 100, 'image/jpeg'),
        ]);

        $response->assertRedirect(route('web.messages.thread', $owner->id));
        $message = Message::first();
        $this->assertNotNull($message->photo_path);
        $this->assertSame('Ini kamarnya ya', $message->body);
    }

    public function test_sending_a_message_broadcasts_message_sent_event_to_receiver_channel(): void
    {
        Event::fake([MessageSent::class]);
        $owner = User::factory()->create(['role' => 'owner']);
        $tenant = User::factory()->create(['role' => 'user']);

        $this->actingAs($tenant, 'sanctum')->postJson('/api/messages', [
            'receiver_id' => $owner->id,
            'body' => 'Halo, masih kosong?',
        ])->assertCreated();

        Event::assertDispatched(MessageSent::class, function (MessageSent $event) use ($owner, $tenant) {
            $channels = $event->broadcastOn();
            return $event->message->receiver_id === $owner->id
                && $event->message->sender_id === $tenant->id
                && $channels[0]->name === 'private-App.Models.User.' . $owner->id;
        });
    }

    public function test_message_broadcast_payload_includes_photo_url_and_sender_name(): void
    {
        Storage::fake('public');
        $sender = User::factory()->create(['name' => 'Bryan']);
        $receiver = User::factory()->create();
        $message = Message::create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'body' => 'Cek foto ini',
            'photo_path' => 'message-photos/test.jpg',
        ]);
        $message->setRelation('sender', $sender);

        $payload = (new MessageSent($message))->broadcastWith();

        $this->assertSame('Bryan', $payload['sender_name']);
        $this->assertStringContainsString('message-photos/test.jpg', $payload['photo_url']);
        $this->assertSame('Cek foto ini', $payload['body']);
    }

    public function test_message_photo_url_accessor_builds_public_storage_url(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();
        $message = Message::create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'body' => '',
            'photo_path' => 'message-photos/test.jpg',
        ]);

        $this->assertStringContainsString('message-photos/test.jpg', $message->photo_url);
    }
}
