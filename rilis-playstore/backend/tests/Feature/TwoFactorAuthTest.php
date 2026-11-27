<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use App\Notifications\TwoFactorCodeNotification;

class TwoFactorAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_without_2fa_enabled_goes_straight_through(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);

        $response = $this->post('/masuk', ['email' => $user->email, 'password' => 'password123']);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('web.home'));
    }

    public function test_login_with_2fa_enabled_requires_code_before_authenticating(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => bcrypt('password123'), 'two_factor_enabled' => true]);

        $response = $this->post('/masuk', ['email' => $user->email, 'password' => 'password123']);

        // Password benar TAPI belum benar-benar login sampai kode diverifikasi.
        $this->assertGuest();
        $response->assertRedirect(route('web.2fa.challenge'));
        Notification::assertSentTo($user, TwoFactorCodeNotification::class);

        $user->refresh();
        $this->assertNotNull($user->two_factor_code);
    }

    public function test_correct_2fa_code_completes_login(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123'), 'two_factor_enabled' => true]);
        $this->post('/masuk', ['email' => $user->email, 'password' => 'password123']);
        $user->refresh();

        $response = $this->post('/verifikasi-2fa', ['code' => $user->two_factor_code]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('web.home'));
    }

    public function test_wrong_2fa_code_is_rejected_and_code_is_consumed(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123'), 'two_factor_enabled' => true]);
        $this->post('/masuk', ['email' => $user->email, 'password' => 'password123']);
        $user->refresh();
        $realCode = $user->two_factor_code;

        $response = $this->post('/verifikasi-2fa', ['code' => '000000']);

        $this->assertGuest();
        $response->assertSessionHasErrors('code');

        // Kode lama dibuang setelah percobaan gagal -- tidak bisa dicoba lagi.
        $user->refresh();
        $this->assertNull($user->two_factor_code);
        $this->assertNotEquals($realCode, '000000');
    }

    public function test_enabling_2fa_requires_confirming_a_code_first(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('web.2fa.enable.start'));
        $user->refresh();
        $this->assertNotNull($user->two_factor_code);
        $this->assertFalse((bool) $user->two_factor_enabled);

        $this->actingAs($user)->post(route('web.2fa.enable.confirm'), ['code' => $user->two_factor_code]);
        $user->refresh();
        $this->assertTrue((bool) $user->two_factor_enabled);
    }

    public function test_disabling_2fa_is_immediate(): void
    {
        $user = User::factory()->create(['two_factor_enabled' => true]);

        $this->actingAs($user)->post(route('web.2fa.disable'));

        $user->refresh();
        $this->assertFalse((bool) $user->two_factor_enabled);
    }

    public function test_api_login_with_2fa_enabled_withholds_token_until_code_verified(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => bcrypt('password123'), 'two_factor_enabled' => true]);

        $response = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password123']);

        $response->assertOk()->assertJson(['requires_2fa' => true]);
        $response->assertJsonMissing(['access_token']);
        Notification::assertSentTo($user, TwoFactorCodeNotification::class);

        $user->refresh();
        $verify = $this->postJson('/api/verify-2fa', ['email' => $user->email, 'code' => $user->two_factor_code]);
        $verify->assertOk()->assertJsonStructure(['access_token', 'user']);
    }

    public function test_api_login_without_2fa_issues_token_immediately(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);

        $response = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password123']);

        $response->assertOk()->assertJsonStructure(['access_token', 'user']);
    }

    public function test_api_2fa_toggle_via_authenticated_endpoints(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/2fa/enable/start')->assertOk();
        $user->refresh();
        $this->assertNotNull($user->two_factor_code);

        $this->actingAs($user, 'sanctum')->postJson('/api/2fa/enable/confirm', ['code' => $user->two_factor_code])
            ->assertOk()->assertJson(['two_factor_enabled' => true]);

        $this->actingAs($user, 'sanctum')->postJson('/api/2fa/disable')
            ->assertOk()->assertJson(['two_factor_enabled' => false]);
    }
}
