<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // GOOGLE_CLIENT_ID kosong di lingkungan test (default) -- tes yang
        // butuh "sudah dikonfigurasi" set config-nya sendiri per tes.
    }

    public function test_google_redirect_shows_friendly_error_when_not_configured(): void
    {
        config(['services.google.client_id' => null]);

        $response = $this->get('/auth/google');

        $response->assertRedirect(route('web.login'));
        $response->assertSessionHasErrors('email');
    }

    public function test_new_user_is_created_from_google_profile(): void
    {
        config(['services.google.client_id' => 'fake-client-id', 'services.google.client_secret' => 'fake-secret']);

        $fakeUser = (new SocialiteUser())->map([
            'id' => 'google-12345',
            'name' => 'Budi Santoso',
            'email' => 'budi.google@example.com',
        ]);
        Socialite::fake('google', $fakeUser);

        $response = $this->get('/auth/google/callback');

        $user = User::where('email', 'budi.google@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('google-12345', $user->google_id);
        $this->assertNotNull($user->email_verified_at, 'Google-verified email should skip our own verification step.');
        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('web.home'));
    }

    public function test_existing_email_password_account_gets_linked_not_duplicated(): void
    {
        config(['services.google.client_id' => 'fake-client-id', 'services.google.client_secret' => 'fake-secret']);

        $existing = User::factory()->create(['email' => 'sudah.daftar@example.com']);

        $fakeUser = (new SocialiteUser())->map([
            'id' => 'google-99999',
            'name' => $existing->name,
            'email' => 'sudah.daftar@example.com',
        ]);
        Socialite::fake('google', $fakeUser);

        $this->get('/auth/google/callback');

        $this->assertDatabaseCount('users', 1);
        $existing->refresh();
        $this->assertSame('google-99999', $existing->google_id);
        $this->assertAuthenticatedAs($existing);
    }

    public function test_google_login_still_requires_2fa_code_when_enabled(): void
    {
        config(['services.google.client_id' => 'fake-client-id', 'services.google.client_secret' => 'fake-secret']);

        $existing = User::factory()->create(['email' => 'aman2fa@example.com', 'two_factor_enabled' => true]);

        $fakeUser = (new SocialiteUser())->map([
            'id' => 'google-2fa',
            'name' => $existing->name,
            'email' => 'aman2fa@example.com',
        ]);
        Socialite::fake('google', $fakeUser);

        $response = $this->get('/auth/google/callback');

        $this->assertGuest();
        $response->assertRedirect(route('web.2fa.challenge'));
    }
}
