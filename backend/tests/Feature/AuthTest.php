<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_registration_creates_default_profile(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/register', [
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'phone' => '081234567890',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'user',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('user.role', 'user')
            ->assertJsonPath('user.profile.gender', 'pria');

        $this->assertDatabaseHas('users', ['email' => 'budi@example.com', 'role' => 'user']);
        $this->assertDatabaseCount('user_profiles', 1);
    }

    public function test_owner_registration_does_not_create_profile(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/register', [
            'name' => 'Pemilik Kos',
            'email' => 'pemilik@example.com',
            'phone' => '081234567891',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'owner',
        ]);

        $response->assertStatus(201)->assertJsonPath('user.role', 'owner');

        $this->assertDatabaseCount('user_profiles', 0);
    }

    public function test_registration_rejects_invalid_phone_format(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '12345', // bukan format nomor HP Indonesia yang valid
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['phone']);
    }

    public function test_registration_sends_verification_email(): void
    {
        Notification::fake();

        $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'verify-me@example.com',
            'phone' => '081234567892',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('email', 'verify-me@example.com')->firstOrFail();
        Notification::assertSentTo($user, \Illuminate\Auth\Notifications\VerifyEmail::class);
    }

    public function test_user_can_login_with_correct_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'login@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)->assertJsonStructure(['access_token', 'user']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create(['email' => 'wrongpass@example.com', 'password' => bcrypt('correct')]);

        $response = $this->postJson('/api/login', [
            'email' => 'wrongpass@example.com',
            'password' => 'incorrect',
        ]);

        $response->assertStatus(401);
    }

    public function test_email_verification_link_marks_user_verified(): void
    {
        $user = User::factory()->unverified()->create();

        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $this->assertNull($user->fresh()->email_verified_at);

        $response = $this->get($url);

        $response->assertStatus(200);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }
}
