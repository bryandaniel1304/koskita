<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AvatarUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_user_can_upload_an_avatar(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/profile/avatar', [
            'avatar' => UploadedFile::fake()->create('me.jpg', 100, 'image/jpeg'),
        ]);

        $response->assertOk();
        $user->refresh();
        $this->assertNotNull($user->avatar_path);
        Storage::disk('public')->assertExists($user->avatar_path);
        $this->assertStringContainsString($user->avatar_path, $response->json('avatar_url'));
    }

    public function test_api_uploading_a_new_avatar_replaces_and_deletes_the_old_one(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/profile/avatar', [
            'avatar' => UploadedFile::fake()->create('first.jpg', 100, 'image/jpeg'),
        ]);
        $firstPath = $user->refresh()->avatar_path;

        $this->actingAs($user, 'sanctum')->postJson('/api/profile/avatar', [
            'avatar' => UploadedFile::fake()->create('second.jpg', 100, 'image/jpeg'),
        ]);

        Storage::disk('public')->assertMissing($firstPath);
        $this->assertNotSame($firstPath, $user->refresh()->avatar_path);
    }

    public function test_api_user_can_delete_their_avatar(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum')->postJson('/api/profile/avatar', [
            'avatar' => UploadedFile::fake()->create('me.jpg', 100, 'image/jpeg'),
        ]);
        $path = $user->refresh()->avatar_path;

        $response = $this->actingAs($user, 'sanctum')->deleteJson('/api/profile/avatar');

        $response->assertOk();
        Storage::disk('public')->assertMissing($path);
        $this->assertNull($user->refresh()->avatar_path);
    }

    public function test_profile_endpoint_includes_null_avatar_url_when_not_set(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/profile');

        $response->assertOk()->assertJsonPath('avatar_url', null);
    }

    public function test_web_user_can_upload_and_delete_an_avatar(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $upload = $this->actingAs($user)->post('/profil/avatar', [
            'avatar' => UploadedFile::fake()->create('me.jpg', 100, 'image/jpeg'),
        ]);
        $upload->assertRedirect();
        $path = $user->refresh()->avatar_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);

        $delete = $this->actingAs($user)->delete('/profil/avatar');
        $delete->assertRedirect();
        Storage::disk('public')->assertMissing($path);
        $this->assertNull($user->refresh()->avatar_path);
    }
}
