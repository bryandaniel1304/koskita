<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use App\Notifications\TwoFactorCodeNotification;

class MobileGoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGoogleTokenInfo(array $overrides = []): void
    {
        Http::fake([
            'oauth2.googleapis.com/tokeninfo*' => Http::response(array_merge([
                'aud' => 'fake-mobile-client-id',
                'sub' => 'google-98765',
                'email' => 'mobile.google@example.com',
                'email_verified' => 'true',
                'name' => 'Budi Mobile',
            ], $overrides)),
        ]);
    }

    public function test_google_config_reports_not_configured_when_client_id_is_empty(): void
    {
        config(['services.google.client_id' => null]);

        $response = $this->getJson('/api/auth/google/config');

        $response->assertOk();
        $response->assertJson(['configured' => false, 'client_id' => null]);
    }

    public function test_google_config_reports_configured_and_exposes_the_public_client_id(): void
    {
        config(['services.google.client_id' => 'fake-mobile-client-id']);

        $response = $this->getJson('/api/auth/google/config');

        $response->assertOk();
        $response->assertJson(['configured' => true, 'client_id' => 'fake-mobile-client-id']);
    }

    public function test_login_with_google_is_rejected_when_not_configured(): void
    {
        config(['services.google.client_id' => null]);

        $response = $this->postJson('/api/auth/google', ['id_token' => 'whatever']);

        $response->assertStatus(503);
    }

    public function test_login_with_google_rejects_a_token_meant_for_a_different_app(): void
    {
        config(['services.google.client_id' => 'fake-mobile-client-id']);
        $this->fakeGoogleTokenInfo(['aud' => 'someone-elses-client-id']);

        $response = $this->postJson('/api/auth/google', ['id_token' => 'stolen-token']);

        $response->assertStatus(401);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_login_with_google_rejects_an_unverified_email(): void
    {
        config(['services.google.client_id' => 'fake-mobile-client-id']);
        $this->fakeGoogleTokenInfo(['email_verified' => 'false']);

        $response = $this->postJson('/api/auth/google', ['id_token' => 'valid-but-unverified']);

        $response->assertStatus(401);
    }

    public function test_new_user_is_created_and_issued_a_token_from_a_valid_google_id_token(): void
    {
        config(['services.google.client_id' => 'fake-mobile-client-id']);
        $this->fakeGoogleTokenInfo();

        $response = $this->postJson('/api/auth/google', ['id_token' => 'valid-token']);

        $response->assertOk();
        $response->assertJsonStructure(['user', 'access_token', 'token_type']);
        $this->assertDatabaseHas('users', [
            'email' => 'mobile.google@example.com',
            'google_id' => 'google-98765',
        ]);
        $user = User::where('email', 'mobile.google@example.com')->first();
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_existing_manual_account_is_linked_by_email_instead_of_duplicated(): void
    {
        config(['services.google.client_id' => 'fake-mobile-client-id']);
        $existing = User::factory()->create(['email' => 'mobile.google@example.com', 'google_id' => null]);
        $this->fakeGoogleTokenInfo();

        $response = $this->postJson('/api/auth/google', ['id_token' => 'valid-token']);

        $response->assertOk();
        $this->assertDatabaseCount('users', 1);
        $this->assertSame($existing->id, $response->json('user.id'));
        $this->assertSame('google-98765', $existing->fresh()->google_id);
    }

    public function test_google_login_still_requires_2fa_when_enabled(): void
    {
        Notification::fake();
        config(['services.google.client_id' => 'fake-mobile-client-id']);
        User::factory()->create(['email' => 'mobile.google@example.com', 'two_factor_enabled' => true]);
        $this->fakeGoogleTokenInfo();

        $response = $this->postJson('/api/auth/google', ['id_token' => 'valid-token']);

        $response->assertOk();
        $response->assertJson(['requires_2fa' => true]);
        $response->assertJsonMissing(['access_token' => true]);
        Notification::assertSentTo(User::where('email', 'mobile.google@example.com')->first(), TwoFactorCodeNotification::class);
    }
}
